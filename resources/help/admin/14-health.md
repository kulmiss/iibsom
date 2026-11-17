# System health

How the server is doing: hosting mode, PHP and database versions, waiting jobs, where photos are stored, email and Google sign-in keys.

- **MISSING** means a setting in the `.env` file is empty. Ask your developer to fill it in.
- **Jobs waiting** should stay low. A high number means the background worker is not running.
- **Auto-post** (only while that switch is ON): which networks have keys and how many ads were shared or failed in the last 24 hours, with the reason.
