import React from 'react';
import { createRoot } from 'react-dom/client';
import AllowList from './components/AllowList';
import FlaggedEmails from './components/FlaggedEmails';
import KickboxApiKeyInput from './components/KickboxApiKeyInput';
import '../../css/admin.css';

// Initialize React components for WooCommerce Settings sections and Analytics page
document.addEventListener('DOMContentLoaded', function() {
	// Get current section from URL
	const urlParams = new URLSearchParams(window.location.search);
	const section = urlParams.get('section') || '';

	// Mount API Key Input component (on API settings section - default)
	const apiKeyContainer = document.getElementById('kickbox-react-api-key-container');
	if (apiKeyContainer) {
		const fieldId = apiKeyContainer.dataset.fieldId || 'kickbox_integration_api_key';
		const initialValue = apiKeyContainer.dataset.initialValue || '';
		const maskedValue = apiKeyContainer.dataset.maskedValue || '';
		const hasSavedKey = apiKeyContainer.dataset.hasSavedKey === 'true';

		const root = createRoot(apiKeyContainer);
		root.render(
			<KickboxApiKeyInput
				fieldId={fieldId}
				initialValue={initialValue}
				maskedValue={maskedValue}
				hasSavedKey={hasSavedKey}
			/>,
		);
	}

	// Mount Allow List component
	const allowlistContainer = document.getElementById('kickbox-react-allowlist-container');
	if (allowlistContainer && section === 'allowlist') {
		const root = createRoot(allowlistContainer);
		root.render(<AllowList settings={{}} />);
	}

	// Mount Flagged Emails component (on standalone page)
	const flaggedPageContainer = document.getElementById('kickbox-flagged-emails-page-container');
	if (flaggedPageContainer) {
		const root = createRoot(flaggedPageContainer);
		// Create dummy function for pending count refresh
		const refreshPendingCount = async () => {
			return 0;
		};
		root.render(<FlaggedEmails onRefreshPendingCount={refreshPendingCount} />);
	}


	// Handle deliverable action confirmation on save
	const deliverableSelect = document.getElementById('kickbox_integration_deliverable_action');

	if (deliverableSelect && section === 'actions') {
		// Find the WooCommerce settings form
		const settingsForm = deliverableSelect.closest('form');

		if (settingsForm) {
			// Define validation function in global scope so it can be called by onsubmit
			window.kickboxValidateDeliverableAction = function() {
				const currentValue = document.getElementById('kickbox_integration_deliverable_action').value;

				if (currentValue === 'block') {
					return confirm(
						'⚠️ Warning: You are about to set Deliverable Emails to "Block".\n\n' +
						'This will prevent almost all customer checkout and account signups and is counterproductive to your business.\n\n' +
						'Deliverable emails are verified as safe to send to and should typically be allowed.\n\n' +
						'Are you sure you want to continue?',
					);
				}

				return true; // Allow submission for other values
			};

			// Set onsubmit attribute
			settingsForm.onsubmit = window.kickboxValidateDeliverableAction;
		}
	}

	// Note: tipTip initialization is handled by individual components after they mount
});
