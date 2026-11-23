$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem

$target = Join-Path (Get-Location) 'docs/BDMS-SRS-v1.1.docx'
$root = Join-Path $env:TEMP ('bdms-appendix-c-' + [Guid]::NewGuid().ToString('N'))
$extract = Join-Path $root 'extract'
$out = Join-Path $root 'updated.docx'
New-Item -ItemType Directory -Path $extract -Force | Out-Null
[System.IO.Compression.ZipFile]::ExtractToDirectory($target, $extract)

$nsUri = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
$dummy = New-Object Xml.XmlDocument
$ns = New-Object Xml.XmlNamespaceManager($dummy.NameTable)
$ns.AddNamespace('w', $nsUri)

function PText([Xml.XmlNode] $p) {
    return (($p.SelectNodes('.//w:t', $script:ns) | ForEach-Object { $_.InnerText }) -join '')
}

function SetPText([Xml.XmlNode] $p, [string] $text) {
    $texts = @($p.SelectNodes('.//w:t', $script:ns))
    if ($texts.Count -eq 0) {
        $run = $p.OwnerDocument.CreateElement('w:r', $script:nsUri)
        $t = $p.OwnerDocument.CreateElement('w:t', $script:nsUri)
        $t.InnerText = $text
        $run.AppendChild($t) | Out-Null
        $p.AppendChild($run) | Out-Null
    } else {
        $texts[0].InnerText = $text
        for ($i = $texts.Count - 1; $i -ge 1; $i--) { $texts[$i].ParentNode.RemoveChild($texts[$i]) | Out-Null }
    }
}

function SetCellText([Xml.XmlNode] $cell, [string] $text) {
    $p = $cell.SelectSingleNode('./w:p', $script:ns)
    if ($null -eq $p) {
        $p = $cell.OwnerDocument.CreateElement('w:p', $script:nsUri)
        $cell.AppendChild($p) | Out-Null
    }
    $texts = @($p.SelectNodes('.//w:t', $script:ns))
    if ([string]::IsNullOrEmpty($text)) {
        foreach ($t in $texts) { $t.ParentNode.RemoveChild($t) | Out-Null }
        return
    }
    if ($texts.Count -eq 0) {
        $run = $p.OwnerDocument.CreateElement('w:r', $script:nsUri)
        $t = $p.OwnerDocument.CreateElement('w:t', $script:nsUri)
        $t.InnerText = $text
        $run.AppendChild($t) | Out-Null
        $p.AppendChild($run) | Out-Null
    } else {
        $texts[0].InnerText = $text
        for ($i = $texts.Count - 1; $i -ge 1; $i--) { $texts[$i].ParentNode.RemoveChild($texts[$i]) | Out-Null }
    }
}

try {
    $docPath = Join-Path $extract 'word/document.xml'
    $xml = New-Object Xml.XmlDocument
    $xml.PreserveWhitespace = $true
    $xml.Load($docPath)
    $allParagraphs = @($xml.SelectNodes('//w:p', $ns))
    $headingC = $allParagraphs | Where-Object { (PText $_) -eq 'Appendix C. Adviser Confirmation Items' } | Select-Object -First 1
    $headingD = $allParagraphs | Where-Object { (PText $_) -eq 'Appendix D. Approval' } | Select-Object -First 1
    if ($null -eq $headingC -or $null -eq $headingD) { throw 'Appendix C or Appendix D heading not found.' }

    $tableD = $null
    $probe = $headingD.NextSibling
    while ($null -ne $probe) {
        if ($probe.LocalName -eq 'tbl') { $tableD = $probe; break }
        $probe = $probe.NextSibling
    }
    if ($null -eq $tableD) { throw 'Appendix D approval table not found.' }

    $newTable = $tableD.CloneNode($true)
    $gridCols = @($newTable.SelectNodes('./w:tblGrid/w:gridCol', $ns))
    for ($i = $gridCols.Count - 1; $i -ge 3; $i--) { $gridCols[$i].ParentNode.RemoveChild($gridCols[$i]) | Out-Null }
    foreach ($col in @($newTable.SelectNodes('./w:tblGrid/w:gridCol', $ns))) { $col.SetAttribute('w', $nsUri, '3120') }

    $rows = @($newTable.SelectNodes('./w:tr', $ns))
    for ($i = $rows.Count - 1; $i -ge 4; $i--) { $rows[$i].ParentNode.RemoveChild($rows[$i]) | Out-Null }
    $rows = @($newTable.SelectNodes('./w:tr', $ns))
    foreach ($row in $rows) {
        $cells = @($row.SelectNodes('./w:tc', $ns))
        for ($i = $cells.Count - 1; $i -ge 3; $i--) { $cells[$i].ParentNode.RemoveChild($cells[$i]) | Out-Null }
        foreach ($cell in @($row.SelectNodes('./w:tc', $ns))) {
            $tcW = $cell.SelectSingleNode('./w:tcPr/w:tcW', $ns)
            if ($null -ne $tcW) { $tcW.SetAttribute('w', $nsUri, '3120') }
        }
    }

    $values = @(
        @('Role', 'Name', 'Signature / Date'),
        @('Project Leader', 'Rellita, Neziel Jean', ''),
        @('Capstone Adviser', 'Bandalan, Rodrick', ''),
        @('Barangay Representative', '(Beneficiary Signatory)', '')
    )
    $rows = @($newTable.SelectNodes('./w:tr', $ns))
    for ($r = 0; $r -lt $rows.Count; $r++) {
        $cells = @($rows[$r].SelectNodes('./w:tc', $ns))
        for ($c = 0; $c -lt 3; $c++) { SetCellText $cells[$c] $values[$r][$c] }
    }

    # Remove all existing Appendix C content between its heading and Appendix D.
    $node = $headingC.NextSibling
    while ($null -ne $node -and $node -ne $headingD) {
        $next = $node.NextSibling
        $node.ParentNode.RemoveChild($node) | Out-Null
        $node = $next
    }
    SetPText $headingC 'Appendix C. Approval - optional'
    $headingC.ParentNode.InsertAfter($newTable, $headingC) | Out-Null

    # Keep the overview sentence consistent with the renamed appendix.
    foreach ($p in @($xml.SelectNodes('//w:p', $ns))) {
        if ((PText $p) -eq 'Section 2 describes the product and its users. Section 3 defines interfaces, functional requirements, non-functional requirements, and database requirements. The appendices summarize the current entity relationships, traceability evidence, and items requiring adviser confirmation.') {
            SetPText $p 'Section 2 describes the product and its users. Section 3 defines interfaces, functional requirements, non-functional requirements, and database requirements. The appendices summarize the current entity relationships, traceability evidence, and optional approval information.'
        }
    }

    # Update the cached Appendix C TOC title; its page number is reconciled after rendering.
    foreach ($p in @($xml.SelectNodes('//w:p', $ns))) {
        $style = $p.SelectSingleNode('./w:pPr/w:pStyle', $ns)
        if ($null -ne $style -and $style.GetAttribute('val', $nsUri) -eq 'TOC1') {
            $texts = @($p.SelectNodes('.//w:t', $ns))
            if ($texts.Count -gt 1 -and $texts[0].InnerText -eq 'Appendix C. Adviser Confirmation Items') {
                $texts[0].InnerText = 'Appendix C. Approval - optional'
            }
        }
    }

    $settings = New-Object Xml.XmlWriterSettings
    $settings.Encoding = New-Object Text.UTF8Encoding($false)
    $settings.Indent = $false
    $writer = [Xml.XmlWriter]::Create($docPath, $settings)
    try { $xml.Save($writer) } finally { $writer.Close() }

    $zip = [System.IO.Compression.ZipFile]::Open($out, [System.IO.Compression.ZipArchiveMode]::Create)
    try {
        foreach ($file in Get-ChildItem -LiteralPath $extract -File -Recurse) {
            $relative = $file.FullName.Substring($extract.Length + 1).Replace('\', '/')
            $entry = $zip.CreateEntry($relative, [System.IO.Compression.CompressionLevel]::Optimal)
            $input = [IO.File]::OpenRead($file.FullName)
            $output = $entry.Open()
            try { $input.CopyTo($output) } finally { $output.Dispose(); $input.Dispose() }
        }
    } finally { $zip.Dispose() }
    Move-Item -LiteralPath $out -Destination $target -Force
    Write-Output 'SAVED'
}
finally {
    if (Test-Path -LiteralPath $root) { Remove-Item -LiteralPath $root -Recurse -Force -ErrorAction SilentlyContinue }
}
