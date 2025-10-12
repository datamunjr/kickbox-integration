import React from 'react';

const LoadingSpinner = ({ message = 'Loading...' }) => {
	return (
		<div style={{
			textAlign: 'center',
			padding: '40px 20px',
			fontSize: '16px',
			color: '#666'
		}}>
			<span className="spinner is-active" style={{
				float: 'none',
				marginRight: '10px',
				verticalAlign: 'top'
			}}></span>
			{message}
		</div>
	);
};

export default LoadingSpinner;

