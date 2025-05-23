<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\JikanApi\JikanApiClient;

// Instantiate the JikanApiClient
$client = new JikanApiClient();

// Test getAnimeById(1)
echo "
--- Get Anime By ID (1) ---
";
$anime1 = $client->getAnimeById(1); // Cowboy Bebop
if ($anime1 && isset($anime1['data'])) {
    echo "Successfully fetched anime with ID 1:\n";
    print_r($anime1['data']);
} else {
    echo "Failed to fetch anime with ID 1 or it was not found.\n";
    if ($anime1 === null) {
        echo "(Client returned null, check logs for errors)\n";
    } else {
        echo "Response structure was not as expected:\n";
        print_r($anime1);
    }
}

// Test getAnimeById(999999) - Non-existent
echo "
--- Get Anime By ID (999999) ---
";
$anime_non_existent = $client->getAnimeById(999999);
if ($anime_non_existent && isset($anime_non_existent['data'])) {
    // This case should ideally not be hit for a truly non-existent ID if the API behaves as expected (e.g. 404)
    echo "Unexpectedly fetched data for non-existent anime ID 999999:\n";
    print_r($anime_non_existent['data']);
} else {
    echo "Correctly failed to fetch or found no data for non-existent anime ID 999999.\n";
    if ($anime_non_existent === null) {
        echo "(Client returned null, this is expected for a 404 or error. Check logs for specific API error like 404 if needed)\n";
    } else {
        echo "Response for non-existent ID (may indicate empty data or specific error structure from API):\n";
        print_r($anime_non_existent);
    }
}

// Test searchAnime('Naruto')
echo "
--- Search Anime (Naruto) ---
";
$search_naruto = $client->searchAnime('Naruto');
if ($search_naruto && isset($search_naruto['data']) && !empty($search_naruto['data'])) {
    echo "Successfully searched for 'Naruto'. Found " . count($search_naruto['data']) . " results (showing details of the first one if available):\n";
    // Print general structure or first result to avoid too much output
    if (isset($search_naruto['data'][0])) {
        echo "Title of first result: " . ($search_naruto['data'][0]['title'] ?? 'N/A') . "\n";
        echo "Synopsis of first result (first 100 chars): " . substr(($search_naruto['data'][0]['synopsis'] ?? 'N/A'), 0, 100) . "...\n";
    } else {
        echo "Search returned data, but it's empty.\n";
    }
    // For full data: print_r($search_naruto['data']);
} else {
    echo "Failed to search for 'Naruto' or no results found.\n";
    if ($search_naruto === null) {
        echo "(Client returned null, check logs for errors)\n";
    } else {
        echo "Response structure was not as expected or data array was empty:\n";
        print_r($search_naruto);
    }
}

// Test searchAnime('NonExistentAnimeQueryString')
echo "
--- Search Anime (NonExistentAnimeQueryString) ---
";
$search_non_existent = $client->searchAnime('NonExistentAnimeQueryString');
if ($search_non_existent && isset($search_non_existent['data']) && empty($search_non_existent['data'])) {
    echo "Correctly found no results for 'NonExistentAnimeQueryString'.\n";
    echo "Data array is empty as expected.\n";
    // print_r($search_non_existent); // Usually shows empty 'data' array
} elseif ($search_non_existent && isset($search_non_existent['data']) && !empty($search_non_existent['data'])) {
    echo "Unexpectedly found results for 'NonExistentAnimeQueryString':\n";
    print_r($search_non_existent['data']);
} else {
    echo "Search for 'NonExistentAnimeQueryString' failed or returned unexpected structure.\n";
    if ($search_non_existent === null) {
        echo "(Client returned null, check logs for errors)\n";
    } else {
        echo "Response structure was not as expected:\n";
        print_r($search_non_existent);
    }
}

?>
