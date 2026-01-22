<?php

declare(strict_types=1);

$message = "Teste GitHub Actions -> Telegram";
sendTelegramMessage($TELEGRAM_BOT_TOKEN, $TELEGRAM_CHAT_ID, $message);
exit;

echo "Início do script\n";

// ===== CONFIG =====
//$WATCHMODE_API_KEY = 'RqK7uGPNcWsV0tZcsxmuLqD3e6CFA9d4uKTm3I6P';
//$TMDB_API_KEY      = '5930d6f8bb9993130a7b0951c262dcb8';
//$TELEGRAM_BOT_TOKEN = '8285049660:AAFfiRiKvUTVno3PtF9NsHUavTDCOa_UZr0';
//$TELEGRAM_CHAT_ID  = '1231341335';
$WATCHMODE_API_KEY = 'WATCHMODE_API_KEY';
$TMDB_API_KEY      = 'TMDB_API_KEY';
$TELEGRAM_BOT_TOKEN = getenv('TELEGRAM_BOT_TOKEN');
$TELEGRAM_CHAT_ID  = getenv('TELEGRAM_CHAT_ID');

$startDate = new DateTime('today');
$endDate   = (clone $startDate)->modify('+6 days');

$START_DATE = $startDate->format('Y-m-d');
$END_DATE   = $endDate->format('Y-m-d');

// IDs de interesse
$ACTORS_IDS = [
    31,     // Tom Hanks
    287,    // Brad Pitt
    6193,   // Leonardo DiCaprio
    85,     // Johnny Depp
    380,    // Robert De Niro
    1158,   // Al Pacino
    5292,   // Denzel Washington
    192,    // Morgan Freeman
    2888,   // Will Smith
    6384,   // Keanu Reeves
    1892,   // Matt Damon
    3894,   // Christian Bale
    1245,   // Scarlett Johansson
    524,    // Natalie Portman
    72129,  // Jennifer Lawrence
    11701,  // Angelina Jolie
    5064,   // Meryl Streep
    54693,  // Emma Stone
    30614,  // Ryan Gosling
];

$DIRECTORS_IDS = [
    488,    // Steven Spielberg
    525,    // Christopher Nolan
    138,    // Quentin Tarantino
    1032,   // Martin Scorsese
    2710,   // James Cameron
    578,    // Ridley Scott
    137427, // Denis Villeneuve
    7467,   // David Fincher
    1776,   // Francis Ford Coppola
    2636,   // Alfred Hitchcock
    240,    // Stanley Kubrick
    108,    // Peter Jackson
];

// ===== FIM CONFIG =====

// ===== FUNÇÃO HTTP =====
function httpGetJson(string $url): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        throw new RuntimeException(curl_error($ch));
    }

    curl_close($ch);

    return json_decode($response, true);
}
// ===== FIM FUNÇÃO HTTP =====

// ===== ENVIAR AO TELEGRAM =====
function sendTelegramMessage(
    string $botToken,
    string $chatId,
    string $message
): void {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

    $payload = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    curl_exec($ch);
    curl_close($ch);
}
// ===== ENVIAR AO TELEGRAM =====

// ===== BUSCAR LANÇAMENTOS DA SEMANA =====
function getWeeklyReleases(
    string $apiKey,
    string $startDate,
    string $endDate
): array {
    $url = "https://api.watchmode.com/v1/releases/"
         . "?apiKey={$apiKey}"
         . "&start_date={$startDate}"
         . "&end_date={$endDate}";

    $data = httpGetJson($url);

    return $data['releases'] ?? [];
}
// ===== FIM BUSCAR LANÇAMENTOS DA SEMANA =====

// ===== NORMALIZAR LANÇAMENTOS =====
function normalizeReleases(array $releases): array
{
    $list = [];

    foreach ($releases as $item) {
        if (empty($item['tmdb_id'])) {
            continue;
        }

        $list[] = [
            'title' => $item['title'],
            'type'  => $item['type'],
            'tmdb_id' => $item['tmdb_id'],

            'streaming' => [
                'name' => $item['source_name'],
                'release_date' => $item['source_release_date'],
                'season' => $item['season_number']
            ],

            'poster_url' => $item['poster_url']
        ];
    }

    return $list;
}
// ===== FIM NORMALIZAR LANÇAMENTOS =====

// ===== BUSCAR CRÉDITOS TMDB =====
function getTmdbCredits(
    int $tmdbId,
    string $type,
    string $apiKey
): array {
    $endpoint = $type === 'tv'
        ? "https://api.themoviedb.org/3/tv/{$tmdbId}/credits"
        : "https://api.themoviedb.org/3/movie/{$tmdbId}/credits";

    $url = "{$endpoint}?api_key={$apiKey}";

    return httpGetJson($url);
}
// ===== FIM BUSCAR CRÉDITOS TMDB =====

// ===== FILTRAR POR ATORES E DIRETORES =====
function filterMatches(
    array $credits,
    array $actorsIds,
    array $directorsIds
): array {
    $matches = [
        'actors' => [],
        'directors' => []
    ];

    // 🎭 Atores
    foreach ($credits['cast'] ?? [] as $actor) {
        if (in_array($actor['id'], $actorsIds, true)) {
            $matches['actors'][] = [
                'id' => $actor['id'],
                'name' => $actor['name']
            ];
        }
    }

    // 🎬 Diretores
    foreach ($credits['crew'] ?? [] as $crew) {
        if (
            $crew['job'] === 'Director' &&
            in_array($crew['id'], $directorsIds, true)
        ) {
            $matches['directors'][] = [
                'id' => $crew['id'],
                'name' => $crew['name']
            ];
        }
    }

    return $matches;
}
// ===== FILTRAR POR ATORES E DIRETORES =====

// ===== PIPELINE PRINCIPAL =====
$releasesRaw = getWeeklyReleases(
    $WATCHMODE_API_KEY,
    $START_DATE,
    $END_DATE
);

$releases = normalizeReleases($releasesRaw);

$alerts = [];

foreach ($releases as $item) {
    $credits = getTmdbCredits(
        $item['tmdb_id'],
        $item['type'],
        $TMDB_API_KEY
    );

    $matches = filterMatches(
        $credits,
        $ACTORS_IDS,
        $DIRECTORS_IDS
    );

    if (empty($matches['actors']) && empty($matches['directors'])) {
        continue;
    }

    $item['matches'] = $matches;
    $alerts[] = $item;
}

if (empty($alerts)) {
    $message = "📅 Esta semana não há lançamentos com os atores ou diretores de interesse.";

    // Console
    echo $message . "\n";

    // Telegram
    sendTelegramMessage($TELEGRAM_BOT_TOKEN, $TELEGRAM_CHAT_ID, $message);

    // Sai do script, se quiser
    exit;
}
// ===== FIM PIPELINE PRINCIPAL =====

// ===== GERAR MENSAGEM =====
foreach ($alerts as $item) {
    $typeLabel = $item['type'] === 'tv' ? 'Série' : 'Filme';

    $message  = "🎬 *Lançamentos da Semana*\n\n";
    $message .= "*{$item['title']}* ({$typeLabel})\n";
    $message .= "📺 {$item['streaming']['name']}\n";
    $date = DateTime::createFromFormat('Y-m-d', $item['streaming']['release_date']);
    $formattedDate = $date ? $date->format('d/m/Y') : $item['streaming']['release_date'];
    $message .= "📅 {$formattedDate}\n";

    if (!empty($item['streaming']['season'])) {
        $message .= "📦 Temporada: {$item['streaming']['season']}\n";
    }

    $message .= "\n⭐ *Destaques:*\n";

    foreach ($item['matches']['actors'] as $actor) {
        $message .= "🎭 Ator: {$actor['name']}\n";
    }

    foreach ($item['matches']['directors'] as $director) {
        $message .= "🎬 Diretor: {$director['name']}\n";
    }

    $message .= "\n🆔 TMDB: {$item['tmdb_id']}\n";

    // 👉 Console
    echo $message;
    echo "---------------------------\n\n";

    // 👉 Telegram
    sendTelegramMessage(
        $TELEGRAM_BOT_TOKEN,
        $TELEGRAM_CHAT_ID,
        $message
    );
}
// ===== GERAR MENSAGEM =====

echo "Fim do script\n";
