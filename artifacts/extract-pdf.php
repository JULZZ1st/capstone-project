<?php
$data = file_get_contents(__DIR__.'/../.playwright-mcp/bank-reconciliation-4.pdf');
preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $data, $matches);
foreach ($matches[1] as $stream) {
    $decoded = @gzuncompress($stream);
    if ($decoded !== false) echo $decoded;
}
