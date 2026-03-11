<?php
function dd($a)
{
    echo '<pre style="padding: 20px; background-color: #292929; color: #008b3588; border: 1px solid #ddd; border-radius: 8px; font-family: monospace; overflow-x: auto;">';
    echo '<code>';
    var_dump($a);
    echo '</code></pre>';
    die();
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? "", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function getNextTicketId($tab): string
{
    $maxId = 0;

    foreach ($tab as $elt) {
        $rawId = (string) ($elt['id'] ?? '');
        if (preg_match('/^TK-(\d+)$/i', trim($rawId), $matches)) {
            $numericId = (int) $matches[1];
            if ($numericId > $maxId) {
                $maxId = $numericId;
            }
        }
    }

    return 'TK-' . (string) ($maxId + 1);
}

function getInitials(?string $name): string
{
    $parts = preg_split('/\s+/', trim((string) $name));
    $initials = '';
    foreach ($parts as $part) {
        if ($part !== '' && $initials === '') {
            $initials .= strtoupper(substr($part, 0, 1));
            continue;
        }
        if ($part !== '' && strlen($initials) < 2) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
    }

    return $initials;
}

function getProjectProgressPercent($contractHours, $usedHours): int
{
    $contract = (float) $contractHours;
    $used = (float) $usedHours;

    if ($contract <= 0) {
        return 0;
    }

    $percent = (int) round(($used / $contract) * 100);

    if ($percent < 0) {
        return 0;
    }

    if ($percent > 100) {
        return 100;
    }

    return $percent;
}

?>
