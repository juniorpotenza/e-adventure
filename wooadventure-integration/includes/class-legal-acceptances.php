<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/legal-acceptances/trait-wcai-legal-acceptances-queries.php';
require_once __DIR__ . '/legal-acceptances/trait-wcai-legal-acceptances-record.php';

class WCAI_Legal_Acceptances {
    use WCAI_Legal_Acceptances_Queries;
    use WCAI_Legal_Acceptances_Record;
}
