<?php

declare(strict_types=1);

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
    19292,   // Adam Sandler
    1158,    // Al Pacino
    11701,   // Angelina Jolie
    84223,   // Anna Kendrick
    1813,    // Anne Hathaway
    4173,    // Anthony Hopkins
    7399,    // Bem Stiller
    287,     // Brad Pitt
    6941,    // Cameron Dias
    112,     // Cate Blanchett
    27319,   // Christoph Waltz
    3894,    // Christian Bale
    5292,    // Denzel Washington
    9824,    // Diane Kruger
    4483,    // Dustin Hoffman
    819,     // Edward Norton
    54693,   // Emma Stone
    7056,    // Emma Thompson
    1278487, // Hannah Waddingham
    448,     // Hilary Swank
    6968,    // Hugh Jackman
    70851,   // Jack Black
    514,     // Jack Nicholson
    1708266, // Jamie Foxx
    58224,   // Jason Sudeikis
    4491,    // Jennifer Aniston
    72129,   // Jennifer Lawrence
    16866,   // Jennifer Lopez
    206,     // Jim Carrey
    8891,    // John Travolta
    85,      // Johnny Depp
    1204,    // Julia Roberts
    1231,    // Julianne Moore
    1378310, // Judie Foster
    204,     // Kate Winslet
    6384,    // Keanu Reeves
    40462,   // Kristen Bell
    6193,    // Leonardo DiCaprio
    103,     // Mark Ruffalo
    1892,    // Matt Damon
    2461,    // Mel Gibson
    5064,    // Meryl Streep
    2232,    // Michael Keaton
    192,     // Morgan Freeman
    524,     // Natalie Portman
    2963,    // Nicolas Cage
    2227,    // Nicole Kidman
    59315,   // Olivia Wilde
    69310,   // Ricardo Darin
    380,     // Robert De Niro
    3223,    // Robert Downey Jr.
    17289,   // Rodrigo Santoro
    934,     // Russell Crowe
    30614,   // Ryan Gosling
    18277,   // Sandra Bullock
    1245,    // Scarlett Johansson
    2228,    // Sean Penn
    500,     // Tom Cruise
    31,      // Tom Hanks
    139,     // Uma Thurman
    52583,   // Wagner Moura
    2888,    // Will Smith
    1920,    // Winona Ryder
    57755,   // Woody Harrelson (último)
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
    510,    // Tim Burton
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

// ===== ESCAPAR CARACTERES MARKDOWN =====
function escapeMarkdown(string $text): string {
    return str_replace(
        ['\\', '_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'],
        ['\\\\','\_','\*','\[','\]','\(','\)','\~','\`','\>','\#','\+','\-','\=','\|','\{','\}','\.','\!'],
        $text
    );
}
// ===== ESCAPAR CARACTERES MARKDOWN =====

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
    $message .= "*" . escapeMarkdown($item['title']) . "* ({$typeLabel})\n";
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
