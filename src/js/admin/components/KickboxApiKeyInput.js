import React, { useState, useEffect } from 'react';

const KickboxApiKeyInput = ({ fieldId, initialValue, maskedValue, hasSavedKey }) => {
	const [displayValue, setDisplayValue] = useState(maskedValue || '');
	const [actualValue, setActualValue] = useState(initialValue || '');
	const [isMasked, setIsMasked] = useState(hasSavedKey);
	const [isLoading, setIsLoading] = useState(false);
	const [isTesting, setIsTesting] = useState(false);

	// Check if the current display value contains asterisks (masked)
	const isCurrentlyMasked = displayValue.includes('*');
	const canTest = !isMasked && displayValue && !isCurrentlyMasked;

	// Initialize tipTip for the help tooltip after component mounts
	useEffect(() => {
		if (typeof jQuery !== 'undefined' && jQuery.fn.tipTip) {
			jQuery('.woocommerce-help-tip').tipTip({
				'attribute': 'data-tip',
				'fadeIn': 50,
				'fadeOut': 50,
				'delay': 200
			});
		}
	}, []);

	const handleToggleVisibility = async () => {
		if (isMasked) {
			// Fetch full API key
			setIsLoading(true);
			try {
				const response = await fetch(kickbox_integration_admin.ajax_url, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams({
						action: 'kickbox_integration_get_full_api_key',
						nonce: kickbox_integration_admin.nonce,
					}),
				});

				const data = await response.json();
				if (data.success) {
					const fullKey = data.data.apiKey;
					setDisplayValue(fullKey);
					setActualValue(fullKey);
					setIsMasked(false);
				} else {
					alert('Failed to fetch API key: ' + (data.data.message || 'Unknown error'));
				}
			} catch (error) {
				alert('Network error while fetching API key.');
			} finally {
				setIsLoading(false);
			}
		} else {
			// Re-mask the key (display only)
			setDisplayValue(maskedValue);
			// Keep actual value unchanged
			setIsMasked(true);
		}
	};

	const handleTestConnection = async () => {
		if (!canTest) {
			alert('Please unhide the API key first to test the connection.');
			return;
		}

		setIsTesting(true);
		try {
			const response = await fetch(kickbox_integration_admin.ajax_url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams({
					action: 'kickbox_integration_test_api',
					nonce: kickbox_integration_admin.nonce,
					api_key: displayValue,
				}),
			});

			const data = await response.json();
			if (data.success) {
				const balanceText = data.data.balance === 'N/A' 
					? 'Balance: N/A (Sandbox Mode)' 
					: `Balance: ${data.data.balance} verifications`;
				const modeText = data.data.sandbox_mode ? 'Sandbox Mode' : 'Live Mode';
				alert(`${data.data.message}\n\n${balanceText}\nMode: ${modeText}`);
			} else {
				alert('API connection failed: ' + (data.data.message || 'Unknown error'));
			}
		} catch (error) {
			alert('Network error. Please try again.');
		} finally {
			setIsTesting(false);
		}
	};

	const handleInputChange = (e) => {
		const newValue = e.target.value;
		setDisplayValue(newValue);
		setActualValue(newValue);
		// If user manually types, consider it unmasked
		if (hasSavedKey && newValue !== maskedValue) {
			setIsMasked(false);
		}
	};

	return (
		<div>
			{/* Hidden input that stores the actual API key value for form submission */}
			<input
				type="hidden"
				name={fieldId}
				id={`${fieldId}_hidden`}
				value={actualValue}
			/>
			
			<div className="kickbox-api-key-field-wrapper" style={{ position: 'relative', display: 'inline-block' }}>
				{/* Display input - shows masked or full value but doesn't submit */}
				<input
					id={fieldId}
					type="text"
					style={{ width: '350px', paddingRight: '40px' }}
					value={displayValue}
					onChange={handleInputChange}
					className="regular-text kickbox-api-key-input"
					placeholder="Enter your Kickbox API key"
					autoComplete="off"
				/>
				{hasSavedKey && (
					<button
						type="button"
						className="button button-link kickbox-toggle-api-key"
						onClick={handleToggleVisibility}
						disabled={isLoading}
						style={{
							position: 'absolute',
							right: '5px',
							top: '50%',
							transform: 'translateY(-50%)',
							padding: '0',
							border: 'none',
							background: 'none',
							cursor: 'pointer',
							textDecoration: 'none',
						}}
						title={isMasked ? 'Show API Key' : 'Hide API Key'}
					>
						<span
							className={`dashicons ${isMasked ? 'dashicons-visibility' : 'dashicons-hidden'}`}
							style={{ verticalAlign: 'middle', color: '#2271b1' }}
						></span>
					</button>
				)}
			</div>
			<div style={{ marginTop: '10px' }}>
				<button
					type="button"
					className="button button-secondary kickbox-test-api-connection"
					onClick={handleTestConnection}
					disabled={!canTest || isTesting}
				>
					{isTesting ? 'Testing...' : 'Test Connection'}
				</button>
				<span
					className="woocommerce-help-tip"
					data-tip="Unhide the API key first to test the connection. Click the eye icon to reveal the full API key. This will not impact your verification balance."
					style={{ verticalAlign: 'middle' }}
				></span>
			</div>
		</div>
	);
};

export default KickboxApiKeyInput;

