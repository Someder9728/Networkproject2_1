# Avatar picker does not open in the lobby

If creating a room works but clicking the avatar button does nothing, check the JavaScript assets served by the lobby before changing the avatar route or database.

## Confirm the cause

- `resources/js/app.js` imports `social-ui.js`, which binds `[data-open-avatar]` to the dialog's `showModal()` method.
- Inspect the script URL loaded by the lobby. If it points to `public/build`, verify that the referenced bundle contains `data-open-avatar`.
- A stale bundle can leave the button without a click listener even when the current source code is correct. The console may show no error.
- Run the build and inspect its result. In the observed incident, `qrcode` was declared in package.json and the lockfile but missing from the local node_modules, so the current sources could not build until dependencies were installed.

## Repair the local assets

From the project directory, run:

```sh
npm ci
npm run build
```

Both commands must complete successfully. Then reload the lobby and verify that its script URL matches the new entry in `public/build/manifest.json`. If developing with Vite instead, start `npm run dev` after installing dependencies.

Do not commit node_modules or generated public/build assets; these paths are intentionally ignored. The Dockerfile already runs `npm ci` followed by `npm run build`, and fails deployment when either command fails.

## Verify the complete flow

1. Create a room and click the avatar button before marking yourself ready.
2. Select a character, face, accessory and background color; confirm the preview updates.
3. Save and confirm that the lobby avatar reflects the selected values.
4. Reopen the picker and confirm the selection persisted.
5. Mark yourself ready; the avatar button should be disabled until ready is cancelled.

The route validates catalog values and session membership. RoomService rejects changes after ready or after the game starts, and Player casts avatar JSON to an array.

For server-side regression checks, run:

```sh
php vendor/bin/pest tests/Feature/SocialTableTest.php
```

The repository's phpunit.xml configures these tests to use SQLite `:memory:`. Ensure that no local override or cached configuration points tests at an existing database before running them.

## Migration

Check `php artisan migrate:status` for `2026_10_09_000001_add_cosmetics_and_whispers`. It adds the nullable players.avatar JSON column and the nullable indexed chat_messages.players_recipient_id column. In the observed incident it was already applied, and no database migration was needed to repair the picker.

Never use `migrate:fresh` to repair stale frontend assets; it deletes existing tables.

## Verified outcome

The original served bundle lacked the avatar listener. After installing missing dependencies and building successfully, a newly created room supported opening the picker, previewing and saving cat/wink/flower/mint, and displaying those values in the lobby. Reopening preserved the selection, and ready disabled editing. SocialTableTest passed 8 tests with 51 assertions. No console errors were observed after repair.
