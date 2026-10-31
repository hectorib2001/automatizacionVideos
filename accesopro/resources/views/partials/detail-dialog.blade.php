{{--
    Ventana emergente para textos largos en las tablas (errores, motivos de descarte).
    Uso: un botón con class="detail-more", data-detail-title="..." y data-detail="texto completo".
    El texto se pone con textContent: nunca se interpreta como HTML.
--}}
<style>
    .detail-more { display: inline; margin-left: 4px; padding: 0; border: 0; background: none; color: #25394F; font-size: 12px; font-weight: 700; text-decoration: underline; cursor: pointer; white-space: nowrap; }
    dialog.detail-dialog { width: min(760px, calc(100vw - 32px)); max-height: 80vh; border: 0; border-radius: 10px; padding: 0; box-shadow: 0 10px 40px rgba(0,0,0,.25); }
    dialog.detail-dialog::backdrop { background: rgba(17, 30, 50, .45); }
    .detail-dialog header { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 14px 18px; border-bottom: 1px solid #E4E8EC; }
    .detail-dialog h3 { margin: 0; font-size: 15px; color: #25394F; }
    .detail-dialog header button { border: 1px solid #D7DCE1; background: #fff; border-radius: 6px; padding: 4px 10px; cursor: pointer; font-size: 13px; }
    .detail-dialog pre { margin: 0; padding: 16px 18px; max-height: calc(80vh - 60px); overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere; font-family: inherit; font-size: 13px; line-height: 1.55; color: #111E32; background: #F8F9FA; }
    .detail-dialog.is-code pre { font-family: ui-monospace, Consolas, monospace; font-size: 12px; }
</style>

<dialog class="detail-dialog" id="detail-dialog" aria-labelledby="detail-dialog-title">
    <header>
        <h3 id="detail-dialog-title">Detalle</h3>
        <button type="button" data-close>Cerrar</button>
    </header>
    <pre id="detail-dialog-body"></pre>
</dialog>

<script>
    (() => {
        const dialog = document.getElementById('detail-dialog');
        const title = document.getElementById('detail-dialog-title');
        const body = document.getElementById('detail-dialog-body');

        document.querySelectorAll('.detail-more').forEach((button) => {
            button.addEventListener('click', () => {
                title.textContent = button.dataset.detailTitle;
                body.textContent = button.dataset.detail;
                dialog.classList.toggle('is-code', button.dataset.detailCode !== undefined);
                dialog.showModal();
            });
        });

        dialog.querySelector('[data-close]').addEventListener('click', () => dialog.close());
        // Clic en el fondo oscuro (fuera del recuadro) también cierra.
        dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    })();
</script>
