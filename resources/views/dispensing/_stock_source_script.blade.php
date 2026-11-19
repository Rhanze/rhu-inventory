<script>
document.addEventListener('DOMContentLoaded', function () {
    const source = document.getElementById('stock_source');
    const inventory = document.getElementById('inventory_id');
    if (!source || !inventory) return;
    source.addEventListener('change', function () {
        for (const option of inventory.options) {
            if (!option.dataset.source) continue;
            option.hidden = option.dataset.source !== source.value;
            option.disabled = option.hidden;
            option.selected = false;
        }
        inventory.value = '';
        inventory.dispatchEvent(new Event('change'));
    });
});
</script>
