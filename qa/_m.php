<?php
$raw = (string) file_get_contents('public_html/manifest.webmanifest');
$j = json_decode($raw, true);
echo "parses: " . (is_array($j) ? 'yes' : 'no') . " icons: " . count($j['icons'] ?? []) . " bytes: " . strlen($raw) . "\n";
echo json_last_error_msg() . "\n";
