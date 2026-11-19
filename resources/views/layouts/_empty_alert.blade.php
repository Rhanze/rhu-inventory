<style>
    .empty-alert { position:fixed;inset:50% auto auto 50%;transform:translate(-50%,-50%);margin:0;width:440px;max-width:calc(100vw - 32px);max-height:calc(100dvh - 32px);padding:28px;border:1px solid #d6e5df;border-radius:20px;background:#fff;color:#334155;font:inherit;font-size:19px;box-shadow:0 20px 48px rgba(15,23,42,.2);overflow:auto;box-sizing:border-box; }
    .empty-alert::backdrop { background:rgba(15,23,42,.45); }
    .empty-alert h2 { margin:0 0 12px;color:#0f172a;font-size:24px;font-weight:700;line-height:1.3; }
    .empty-alert p { margin:0 0 24px;font-size:19px;line-height:1.6;overflow-wrap:anywhere; }
    .empty-alert form { display:flex;justify-content:flex-end;margin:0; }
    .empty-alert button { min-width:88px;min-height:48px;padding:10px 22px;border:1px solid #258663;border-radius:10px;background:#258663;color:#fff;font:inherit;font-size:18px;font-weight:600;cursor:pointer; }
    .empty-alert button:hover { background:#1d6e51; }
    .empty-alert button:focus-visible { outline:3px solid #0f766e;outline-offset:3px; }
</style>
<dialog class="empty-alert" id="empty-result-alert" aria-labelledby="empty-result-title" aria-describedby="empty-result-message">
    <h2 id="empty-result-title">No records found</h2>
    <p id="empty-result-message">No existing history yet.</p>
    <form method="dialog"><button type="submit" autofocus>OK</button></form>
</dialog>
