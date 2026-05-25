$path = "d:\_Site\ssotansum\ncd\admin\import_hdc.php"
$content = [System.IO.File]::ReadAllText($path, [System.Text.Encoding]::UTF8)
$target = '</html>style="text-align: center;">'
$idx = $content.IndexOf($target)
if ($idx -ge 0) {
    $newContent = $content.Substring(0, $idx) + '</html>'
    [System.IO.File]::WriteAllText($path, $newContent, [System.Text.Encoding]::UTF8)
    Write-Output "Successfully cleaned up."
} else {
    Write-Output "Target not found."
}
