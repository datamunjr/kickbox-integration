import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
import FlaggedEmailsModal from './components/FlaggedEmailsModal';

const FlaggedEmailsModalApp = () => {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [emailData, setEmailData] = useState(null);

    const openModal = (data) => {
        setEmailData(data);
        setIsModalOpen(true);
    };

    const closeModal = () => {
        setIsModalOpen(false);
        setEmailData(null);
    };

    const displayAdminNotice = (type, message) => {
        // Create notice element
        const notice = document.createElement('div');
        notice.className = `notice notice-${type} is-dismissible`;
        notice.innerHTML = `<p>${message}</p>`;
        
        // Insert at the top of the page content
        const pageContent = document.querySelector('.wrap');
        if (pageContent) {
            pageContent.insertBefore(notice, pageContent.firstChild);
        }
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notice.parentNode) {
                notice.parentNode.removeChild(notice);
            }
        }, 5000);
    };

    const handleSubmit = async (formData) => {
        try {
            const formDataToSend = new FormData();
            formDataToSend.append('action', 'kickbox_save_flagged_email_decision');
            formDataToSend.append('kickbox_item_id', formData.item_id);
            formDataToSend.append('kickbox_decision', formData.decision);
            formDataToSend.append('kickbox_admin_notes', formData.notes);
            formDataToSend.append('kickbox_modal_nonce', kickboxAjax.modalNonce);

            const response = await fetch(kickboxAjax.ajaxurl, {
                method: 'POST',
                body: formDataToSend
            });

            const result = await response.json();

            if (result.success) {
                // Display success notice immediately
                displayAdminNotice('success', result.data.message);
                // Close modal and reload page to show updated data
                closeModal();
                window.location.reload();
            } else {
                // Display error notice immediately
                displayAdminNotice('error', result.data?.message || 'Failed to save decision');
            }
        } catch (error) {
            displayAdminNotice('error', 'Error saving decision: ' + error.message);
        }
    };

    // Make functions globally available
    window.kickboxModal = {
        openModal,
        closeModal,
        handleSubmit
    };

    return (
        <FlaggedEmailsModal
            isOpen={isModalOpen}
            onClose={closeModal}
            emailData={emailData}
            onSubmit={handleSubmit}
        />
    );
};

// Initialize the modal app
document.addEventListener('DOMContentLoaded', () => {
    const container = document.createElement('div');
    container.id = 'kickbox-modal-root';
    document.body.appendChild(container);

    const root = createRoot(container);
    root.render(<FlaggedEmailsModalApp />);
});
