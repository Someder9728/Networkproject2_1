import QRCode from 'qrcode';

export function initChatExtras({form, input, url, csrfToken, channel, canSend, refresh}) {
    const typing = document.createElement('div');
    typing.setAttribute('aria-live', 'polite');
    typing.style.cssText = 'min-height:20px;font-size:12px;color:#94a3b8';
    form.before(typing);
    let lastTyping = 0;
    const headers = {'Accept':'application/json','X-CSRF-TOKEN':csrfToken};
    async function signal(active) {
        if (!canSend()) return;
        try { await fetch(`${url}/typing`, {method:'POST', credentials:'same-origin',headers:{...headers,'Content-Type':'application/json'},body:JSON.stringify({channel:channel(),active})}); } catch {}
    }
    input.addEventListener('input', () => {if (Date.now()-lastTyping > 2000 || !input.value) {lastTyping=Date.now();signal(Boolean(input.value));}});
    input.addEventListener('blur', () => signal(false));
    form.addEventListener('submit', () => signal(false));
    let polling = false;
    const poll = setInterval(async () => {
        if (polling || document.hidden) return;
        polling = true;
        const selected = channel();
        try {
            const response = await fetch(`${url}/typing`, {credentials:'same-origin',headers,cache:'no-store'});
            if (response.ok && selected === channel()) { const data=await response.json(); const names=data.typing?.[selected] ?? []; typing.textContent=names.length ? `${names.join(', ')} กำลังพิมพ์…` : ''; }
        } catch {} finally {polling=false;}
    }, 2500);
    const box = document.createElement('div');
    box.style.cssText='display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:8px 0';
    const record = document.createElement('button'); record.type='button';record.textContent='🎙 อัดเสียง';
    const send = document.createElement('button');send.type='button';send.textContent='ส่งเสียง';send.hidden=true;
    const cancel = document.createElement('button');cancel.type='button';cancel.textContent='ยกเลิก';cancel.hidden=true;
    const preview = document.createElement('audio');preview.controls=true;preview.hidden=true;preview.style.cssText='width:100%;height:36px';
    const status = document.createElement('span');status.setAttribute('aria-live','polite');status.style.fontSize='12px';
    box.append(record,send,cancel,status,preview);form.after(box);
    let recorder, stream, chunks=[], blob, objectUrl, timer, elapsed, recordedChannel, busy=false, cancelled=false;
    function clear() {
        clearInterval(timer);stream?.getTracks().forEach(track=>track.stop());stream=null;
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl=null;blob=null;preview.removeAttribute('src');preview.hidden=true;send.hidden=true;cancel.hidden=true;record.textContent='🎙 อัดเสียง';record.disabled=false;
    }
    record.addEventListener('click',async()=>{
        if (recorder?.state==='recording') {recorder.stop();return;}
        if (!canSend() || busy) {status.textContent='ส่งเสียงในช่องนี้ตอนนี้ไม่ได้';return;}
        if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {status.textContent='อัดเสียงต้องใช้เบราว์เซอร์ที่รองรับและเปิดผ่าน HTTPS';return;}
        clear();
        record.disabled=true;
        try {
            stream=await navigator.mediaDevices.getUserMedia({audio:true});
            if (!canSend()) {clear();return;}
            recordedChannel=channel();cancelled=false;chunks=[];elapsed=0;
            const mime=['audio/webm;codecs=opus','audio/ogg;codecs=opus','audio/mp4'].find(type=>MediaRecorder.isTypeSupported(type));
            recorder=new MediaRecorder(stream,mime ? {mimeType:mime} : undefined);
            recorder.ondataavailable=event=>{if(event.data.size)chunks.push(event.data);};
            recorder.onstop=()=>{
                clearInterval(timer);stream?.getTracks().forEach(track=>track.stop());stream=null;
                record.textContent='🎙 อัดใหม่';record.disabled=false;
                if(cancelled) {clear();return;}
                blob=new Blob(chunks,{type:recorder.mimeType});
                if(blob.size>2*1024*1024 || !blob.size){clear();status.textContent='ไฟล์เสียงใหญ่เกิน 2 MB หรือไม่มีเสียง กรุณาอัดใหม่';return;}
                if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=URL.createObjectURL(blob);preview.src=objectUrl;preview.hidden=false;send.hidden=false;cancel.hidden=false;status.textContent='ฟังก่อนส่งได้';
            };
            recorder.start();record.disabled=false;record.textContent='⏹ หยุดอัด';cancel.hidden=false;status.textContent='กำลังอัด 0 / 30 วินาที';
            timer=setInterval(()=>{elapsed++;status.textContent=`กำลังอัด ${elapsed} / 30 วินาที`;if(elapsed>=30 && recorder.state==='recording')recorder.stop();},1000);
        }catch {clear();status.textContent='เปิดไมโครโฟนไม่สำเร็จ กรุณาอนุญาตไมโครโฟน';}
    });
    cancel.addEventListener('click',()=>{cancelled=true;if(recorder?.state==='recording')recorder.stop();else clear();status.textContent='';});
    send.addEventListener('click',async()=>{
        if (!blob || busy) return;
        if(!canSend() || channel()!==recordedChannel){status.textContent='ช่องแชทหรือเฟสเปลี่ยนแล้ว กรุณาอัดใหม่';return;}
        busy=true;send.disabled=true;record.disabled=true;cancel.disabled=true;preview.pause();
        const data=new FormData();data.append('channel',recordedChannel);data.append('audio',blob,blob.type.includes('mp4')?'voice.mp4':blob.type.includes('ogg')?'voice.ogg':'voice.webm');
        try {const response=await fetch(`${url}/voice`,{method:'POST',credentials:'same-origin',headers,body:data});if(!response.ok)throw Error();clear();status.textContent='ส่งเสียงแล้ว';await refresh(false);}
        catch {status.textContent='ส่งเสียงไม่สำเร็จ กรุณาลองอีกครั้ง';}
        finally{busy=false;send.disabled=false;record.disabled=false;cancel.disabled=false;}
    });
    window.addEventListener('pagehide',()=>{clearInterval(poll);cancelled=true;if(recorder?.state==='recording')recorder.stop();clear();});
}

const roomCode=document.querySelector('meta[name="room-code"]')?.content;
if(roomCode) {
    const anchor=document.getElementById('copy-room-btn')?.parentElement ?? document.querySelector('.room-code');
    if(anchor) {
        const button=document.createElement('button');button.type='button';button.textContent='▦ QR เข้าห้อง';button.style.cssText='margin-left:8px;border:1px solid #64748b;border-radius:8px;padding:5px 8px;background:#172033;color:#fff;font-size:12px';anchor.append(button);
        const dialog=document.createElement('dialog');dialog.style.cssText='border:1px solid #64748b;border-radius:16px;padding:24px;max-width:90vw;background:#fff;color:#172033;text-align:center';
        const heading=document.createElement('h3');heading.textContent=`สแกนเข้าห้อง ${roomCode}`;
        const canvas=document.createElement('canvas');const link=document.createElement('a');link.href=new URL(`/room-test/${encodeURIComponent(roomCode)}`,location.origin).href;link.textContent='เปิดลิงก์เข้าห้อง';link.style.display='block';
        const hint=document.createElement('p');hint.textContent=/^(localhost|127\.|\[::1\])/.test(location.hostname)?'เปิดเกมด้วย IP หรือโดเมนที่มือถือเข้าถึงได้ก่อนแชร์ QR':'มือถือจะต้องเข้าถึงที่อยู่ของเซิร์ฟเวอร์นี้ได้';hint.style.fontSize='12px';
        const close=document.createElement('button');close.type='button';close.textContent='ปิด';close.onclick=()=>dialog.close();
        dialog.append(heading,canvas,link,hint,close);document.body.append(dialog);
        button.onclick=async()=>{try{await QRCode.toCanvas(canvas,link.href,{width:220,margin:2,errorCorrectionLevel:'M'});dialog.showModal();}catch{hint.textContent='สร้าง QR ไม่สำเร็จ กรุณาใช้ลิงก์เข้าห้อง';dialog.showModal();}};
    }
}
