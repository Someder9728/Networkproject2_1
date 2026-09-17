# Database Architecture
ภาพรวมสถาปัตยกรรม

## 4 ตารางหลัก
- 1. rooms: เก็บข้อมูลห้อง สภาพเกมปัจจุบัน และตัวนับเวลา (Phase Timer)
- 2. players: เก็บข้อมูลผู้เล่นชั่วคราว บทบาท สิทธิ์ Host และสถานะชีวิต/การเชื่อมต่อ
- 3. votes_and_actions: บันทึกประวัติการโหวตและการใช้ความสามารถพิเศษตาม Role ในแต่ละคืน/วัน
- 4. chat_messages: บันทึกข้อความแชต คัดกรองการเห็นข้อความตาม Channel (All ตอนกลางวัน, Werewolf, คนตาย)

## Table Schemas
1. rooms:
- room_id | INT | PK
- room_code | CHAR(6) | NN, Unique
- room_status | ENUM('waiting', 'day_discussion', 'day_voting', 'night', 'ended') | NN
- room_phase_end_time | TIMESTAMP | Nullable
- created_at | TIMESTAMP | NN, Default CURRENT_TIMESTAMP
- difficulty | ENUM('easy', 'hard') | NN, Default 'easy'

2. players:
- player_id | INT | PK, NN, AI
- player_name | VARCHAR(45) | NN
- is_host | TINYINT(1) | NN, Default 0
- session_token | VARCHAR(45) | NN, Unique
- role | ENUM('villager', 'werewolf', 'seer') | Nullable
- is_alive | TINYINT(1) | NN, Default 1
- is_connected | TINYINT(1) | NN, Default 1
- rooms_room_id | INT | FK, NN

3. votes_and_actions:
- id | INT | PK, NN, AI
- rooms_room_id | INT | FK, NN
- phase_number | INT | NN, Default 1  
- phase_type | ENUM('night', 'day') | NN
- action_type | ENUM('vote_lynch', 'werewolf_kill', 'seer_check') | NN
- players_voter_id | INT | FK, NN
- players_target_id | INT | FK, NN

4. chat_messages:
- chat_id | INT | PK, NN, AI
- rooms_room_id | INT | FK, NN
- players_sender_id | INT | FK, NN
- chat_channel | ENUM('all', 'werewolf', 'dead') | NN, Default 'all'
- message | TEXT | NN
- created_at | TIMESTAMP | NN, Default CURRENT_TIMESTAMP


## ความสัมพันธ์
- rooms ➔ players (1:N, เส้นประ) CASCADE: เมื่อลบห้อง ข้อมูลผู้เล่นในห้องจะถูกลบทิ้งอัตโนมัติ (ON DELETE CASCADE)
rooms_room_id
- rooms ➔ votes_and_actions (1:N, เส้นประ)
rooms_room_id
- rooms ➔ chat_messages (1:N, เส้นประ)
rooms_room_id
- players ➔ votes_and_actions (1:N, เส้นประ 2 เส้น)
players_voter_id และ players_target_id
- players ➔ chat_messages (1:N, เส้นประ)
players_sender_id

## SQL DDL Script (สำหรับ Create Database)
CREATE TABLE rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    room_code CHAR(6) NOT NULL UNIQUE,
    room_status ENUM('waiting', 'day_discussion', 'day_voting', 'night', 'ended') NOT NULL DEFAULT 'waiting',
    room_phase_end_time TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    difficulty ENUM('easy', 'hard') NOT NULL DEFAULT 'easy'
);

CREATE TABLE players (
    player_id INT AUTO_INCREMENT PRIMARY KEY,
    player_name VARCHAR(45) NOT NULL,
    is_host TINYINT(1) NOT NULL DEFAULT 0,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    role ENUM('villager', 'werewolf', 'seer') NULL,
    is_alive TINYINT(1) NOT NULL DEFAULT 1,
    is_connected TINYINT(1) NOT NULL DEFAULT 1,
    rooms_room_id INT NOT NULL,
    FOREIGN KEY (rooms_room_id) REFERENCES rooms(room_id) ON DELETE CASCADE
);

CREATE TABLE votes_and_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rooms_room_id INT NOT NULL,
    phase_number INT NOT NULL DEFAULT 1,
    phase_type ENUM('night', 'day_voting') NOT NULL,
    voter_id INT NOT NULL,
    target_id INT NOT NULL,
    action_type ENUM('vote_lynch', 'werewolf_kill', 'seer_check') NOT NULL,
    players_voter_id INT NOT NULL,
    players_target_id INT NOT NULL,
    FOREIGN KEY (rooms_room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    FOREIGN KEY (players_voter_id) REFERENCES players(player_id) ON DELETE CASCADE,
    FOREIGN KEY (players_target_id) REFERENCES players(player_id) ON DELETE CASCADE
);

CREATE TABLE chat_messages (
    chat_id INT AUTO_INCREMENT PRIMARY KEY,
    rooms_room_id INT NOT NULL,
    players_sender_id INT NOT NULL,
    chat_channel ENUM('all', 'werewolf', 'dead') NOT NULL DEFAULT 'all',
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rooms_room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    FOREIGN KEY (players_sender_id) REFERENCES players(player_id) ON DELETE CASCADE
);