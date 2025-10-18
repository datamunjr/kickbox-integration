import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import EmailVerificationReport from './EmailVerificationReport';

addFilter(
	'woocommerce_admin_reports_list',
	'analytics/kickbox-email-verifications',
	( reports ) => {
		reports.push( {
			report: 'kickbox-email-verifications',
			title: __( 'Kickbox Email Verifications', 'kickbox-integration' ),
			component: EmailVerificationReport,
		} );
		return reports;
} );