<?php
// Setup wizard document head on the shared standalone layout (no Config.php).
require_once __DIR__ . '/../../errors/template.php';

$rootPath = $GLOBALS['CHURCHCRM_SETUP_ROOT_PATH'] ?? '';
$nonce = base64_encode(random_bytes(16));

renderStandaloneHeader($rootPath, $sPageTitle ?? 'ChurchCRM – Setup', 'setup');
?>
    <script nonce="<?= $nonce ?>">
        window.CRM = { root: <?= json_encode($rootPath, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?> };
    </script>
    <script src="<?= standaloneEscape($rootPath . '/skin/v2/setup.min.js') ?>" defer></script>
