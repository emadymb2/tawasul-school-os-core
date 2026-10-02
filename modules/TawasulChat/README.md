# TawasulChat — retired

The chat module was merged into **TawasulMessenger**. The live code now lives at
`modules/TawasulMessenger/`:

| Was | Is now |
|---|---|
| `chat.php` | `messenger_chat.php` |
| `chat_ajax.php` | `messenger_chat_ajax.php` |
| `chat_new.php` / `chat_newProcess.php` | `messenger_chat_new.php` / `messenger_chat_newProcess.php` |
| `chat_starred.php` | `messenger_chat_starred.php` |
| `chat_settings.php` / `chat_settingsProcess.php` | `messenger_chat_settings.php` / `messenger_chat_settingsProcess.php` |
| `chat_group_manage.php` / `…Process.php` | `messenger_chat_group_manage.php` / `…Process.php` |
| `moduleFunctions.php` | `chatFunctions.php` |
| `css/module.css` | `css/chat.css` |
| `js/chat.js` | `js/messenger_chat.js` |
| `js/contactSearch.js` | `js/messenger_chat_contactSearch.js` |
| `src/` | `src/Chat/` |
| `schema.php`, `CHANGEDB.php` | `TawasulMessenger/` (same names) |

The database tables were renamed at the same time, from `tawasulChat*` to
`tawasulMessengerChat*`. The `tawasulChat*ID` **column** names were deliberately
left alone: they are not table names, and renaming them would have meant
rewriting every column reference for no benefit.

The nine PHP files still here are 302/307 forwarders and nothing else. They keep
old bookmarks, notification links and cached menu entries working. There is no
`manifest.php`, so the platform no longer discovers this folder as a module and
it does not appear in the menu.

Once you are confident nothing links here any more, this folder can be deleted.
