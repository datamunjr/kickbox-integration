document.addEventListener('DOMContentLoaded', function() {
    // Handle clicks on decision column values
    document.addEventListener('click', function(event) {
        if (event.target.closest('.kickbox-decision-clickable')) {
            const itemId = event.target.closest('.kickbox-decision-clickable').dataset.itemId;
            
            // Get item data via AJAX using fetch
            const formData = new FormData();
            formData.append('action', 'kickbox_get_flagged_email_details');
            formData.append('item_id', itemId);
            formData.append('nonce', kickboxAjax.nonce);
            
            fetch(kickboxAjax.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Open modal with data
                    if (window.kickboxModal && window.kickboxModal.openModal) {
                        window.kickboxModal.openModal(data.data);
                    }
                } else {
                    alert('Error loading email details: ' + (data.data?.message || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Error loading email details: ' + error.message);
            });
        }
    });
});
