document.addEventListener('click', (e) => {
    if (e.target.classList.contains('selected-option')) {
        document.querySelector('.woo_discount_free_select').classList.toggle('open');
    }
});

document.addEventListener('click', (e) => {
    if (e.target && e.target.matches('.woo_discount_free_select .option a')) {
        e.preventDefault();

        let product_id = e.target.dataset.id;

        let data = new FormData();
        data.append('action', 'woo_add');
        data.append('product_id', product_id);

        fetch(woo_discount.url, {
            method: 'POST',
            body: data,
        })
            .then(response => response.json())
            .then(data => {
                location.reload();
            })
            .catch(error => console.error('Error when calling a function:', error))
    }
})
