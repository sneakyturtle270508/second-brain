<?php

function cosine_similarity(array $a, array $b): float
{
    if (count($a) !== count($b) || count($a) === 0) {
        return 0.0;
    }

    $dot = $na = $nb = 0.0;

    foreach ($a as $i => $v) {
        $dot += $v * $b[$i];
        $na += $v * $v;
        $nb += $b[$i] * $b[$i];
    }

    $den = sqrt($na) * sqrt($nb);
    return $den > 0 ? $dot / $den : 0.0;
}
