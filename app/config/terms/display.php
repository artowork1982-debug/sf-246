<?php
/**
 * Display & Playlist Terms - Xibo information display integration
 * TTL selectors, playlist status, and related terms
 */
return [
    // Display duration (Publish Modal)
    'display_duration_heading' => [
        'fi' => 'Näyttökesto infonäytöllä',
        'sv' => 'Visningstid på informationsskärm',
        'en' => 'Display duration on info screen',
        'it' => 'Durata di visualizzazione sul display',
        'el' => 'Διάρκεια εμφάνισης στην οθόνη πληροφοριών',
    ],
    'display_duration_description' => [
        'fi' => 'Valitse kuinka monta sekuntia tämä kuva näytetään Xibo-näytöillä',
        'sv' => 'Välj hur många sekunder denna bild visas på Xibo-skärmar',
        'en' => 'Select how many seconds this image is shown on Xibo screens',
        'it' => 'Seleziona quanti secondi questa immagine viene visualizzata sui display Xibo',
        'el' => 'Επιλέξτε πόσα δευτερόλεπτα θα εμφανίζεται αυτή η εικόνα στις οθόνες Xibo',
    ],
    'duration_10s' => ['fi' => '10 s', 'sv' => '10 s', 'en' => '10 s', 'it' => '10 s', 'el' => '10 δ'],
    'duration_15s' => ['fi' => '15 s', 'sv' => '15 s', 'en' => '15 s', 'it' => '15 s', 'el' => '15 δ'],
    'duration_20s' => ['fi' => '20 s', 'sv' => '20 s', 'en' => '20 s', 'it' => '20 s', 'el' => '20 δ'],
    'duration_30s' => ['fi' => '30 s', 'sv' => '30 s', 'en' => '30 s', 'it' => '30 s', 'el' => '30 δ'],
    'duration_45s' => ['fi' => '45 s', 'sv' => '45 s', 'en' => '45 s', 'it' => '45 s', 'el' => '45 δ'],
    'duration_60s' => ['fi' => '60 s', 'sv' => '60 s', 'en' => '60 s', 'it' => '60 s', 'el' => '60 δ'],

    // Per-language display selection (Publish Modal)
    'display_showing_lang_displays' => [
        'fi' => 'Näytetään tämän kielen näytöt',
        'sv' => 'Visar skärmar för detta språk',
        'en' => 'Showing displays for this language',
        'it' => 'Visualizzazione dei display per questa lingua',
        'el' => 'Εμφάνιση οθονών για αυτή τη γλώσσα',
    ],
    'display_per_language_info' => [
        'fi' => 'Jokaisella kieliversiolla on omat näyttövalintansa',
        'sv' => 'Varje språkversion har sina egna skärmval',
        'en' => 'Each language version has its own display selections',
        'it' => 'Ogni versione linguistica ha le proprie selezioni di display',
        'el' => 'Κάθε γλωσσική έκδοση έχει τις δικές της επιλογές οθόνης',
    ],

    // TTL Selector (Publish Modal)
    'display_ttl_heading' => [
        'fi' => 'Näkyvyysaika infonäytöillä',
        'sv' => 'Visningstid på informationsskärmar',
        'en' => 'Display time on info screens',
        'it' => 'Tempo di visualizzazione sui display',
        'el' => 'Χρόνος εμφάνισης στις οθόνες πληροφοριών',
    ],
    'display_ttl_description' => [
        'fi' => 'Valitse kuinka kauan flash näytetään Xibo-infonäytöillä',
        'sv' => 'Välj hur länge flash visas på Xibo-informationsskärmar',
        'en' => 'Select how long the flash is displayed on Xibo info screens',
        'it' => 'Seleziona per quanto tempo il flash viene visualizzato sui display Xibo',
        'el' => 'Επιλέξτε πόσο χρόνο θα εμφανίζεται το flash στις οθόνες Xibo',
    ],
    'ttl_no_limit' => [
        'fi' => 'Ei aikarajaa',
        'sv' => 'Ingen tidsgräns',
        'en' => 'No time limit',
        'it' => 'Nessun limite di tempo',
        'el' => 'Χωρίς χρονικό όριο',
    ],
    'ttl_1_week' => [
        'fi' => '1 viikko',
        'sv' => '1 vecka',
        'en' => '1 week',
        'it' => '1 settimana',
        'el' => '1 εβδομάδα',
    ],
    'ttl_2_weeks' => [
        'fi' => '2 viikkoa',
        'sv' => '2 veckor',
        'en' => '2 weeks',
        'it' => '2 settimane',
        'el' => '2 εβδομάδες',
    ],
    'ttl_1_month' => [
        'fi' => '1 kuukausi',
        'sv' => '1 månad',
        'en' => '1 month',
        'it' => '1 mese',
        'el' => '1 μήνας',
    ],
    'ttl_2_months' => [
        'fi' => '2 kuukautta',
        'sv' => '2 månader',
        'en' => '2 months',
        'it' => '2 mesi',
        'el' => '2 μήνες',
    ],
    'ttl_3_months' => [
        'fi' => '3 kuukautta',
        'sv' => '3 månader',
        'en' => '3 months',
        'it' => '3 mesi',
        'el' => '3 μήνες',
    ],
    'ttl_preview_default' => [
        'fi' => 'Vanhenee',
        'sv' => 'Utgår',
        'en' => 'Expires',
        'it' => 'Scade',
        'el' => 'Λήγει',
    ],
    
    // Playlist Status (View Page)
    'playlist_status_active' => [
        'fi' => 'Näytetään infonäytöillä',
        'sv' => 'Visas på informationsskärmar',
        'en' => 'Shown on info screens',
        'it' => 'Mostrato sui display',
        'el' => 'Εμφανίζεται στις οθόνες πληροφοριών',
    ],
    'playlist_status_expired' => [
        'fi' => 'Vanhentunut',
        'sv' => 'Utgången',
        'en' => 'Expired',
        'it' => 'Scaduto',
        'el' => 'Ληγμένο',
    ],
    'playlist_status_removed' => [
        'fi' => 'Poistettu playlistasta',
        'sv' => 'Borttagen från spellistan',
        'en' => 'Removed from playlist',
        'it' => 'Rimosso dalla playlist',
        'el' => 'Αφαιρέθηκε από τη λίστα αναπαραγωγής',
    ],
    'playlist_expires_in_days' => [
        'fi' => 'Vanhenee %d päivän kuluttua',
        'sv' => 'Utgår om %d dagar',
        'en' => 'Expires in %d days',
        'it' => 'Scade tra %d giorni',
        'el' => 'Λήγει σε %d ημέρες',
    ],
    'playlist_expires_today' => [
        'fi' => 'Vanhenee tänään',
        'sv' => 'Utgår idag',
        'en' => 'Expires today',
        'it' => 'Scade oggi',
        'el' => 'Λήγει σήμερα',
    ],
    'playlist_no_expiry' => [
        'fi' => 'Ei vanhenemisaikaa',
        'sv' => 'Inget utgångsdatum',
        'en' => 'No expiration',
        'it' => 'Nessuna scadenza',
        'el' => 'Χωρίς λήξη',
    ],
    'playlist_expired_at' => [
        'fi' => 'Vanheni',
        'sv' => 'Utgick',
        'en' => 'Expired',
        'it' => 'Scaduto',
        'el' => 'Έληξε',
    ],
    'playlist_removed_at' => [
        'fi' => 'Poistettu',
        'sv' => 'Borttagen',
        'en' => 'Removed',
        'it' => 'Rimosso',
        'el' => 'Αφαιρέθηκε',
    ],
    
    // Action Buttons
    'btn_remove_from_playlist' => [
        'fi' => 'Poista playlistasta',
        'sv' => 'Ta bort från spellistan',
        'en' => 'Remove from playlist',
        'it' => 'Rimuovi dalla playlist',
        'el' => 'Αφαίρεση από τη λίστα',
    ],
    'btn_restore_to_playlist' => [
        'fi' => 'Palauta playlistaan',
        'sv' => 'Återställ till spellistan',
        'en' => 'Restore to playlist',
        'it' => 'Ripristina nella playlist',
        'el' => 'Επαναφορά στη λίστα',
    ],
    
    // Confirmation Messages
    'confirm_remove_from_playlist' => [
        'fi' => 'Haluatko varmasti poistaa flashin infonäyttö-playlistasta?',
        'sv' => 'Vill du verkligen ta bort flash från informationsskärmens spellista?',
        'en' => 'Are you sure you want to remove the flash from the info screen playlist?',
        'it' => 'Sei sicuro di voler rimuovere il flash dalla playlist del display?',
        'el' => 'Είστε βέβαιοι ότι θέλετε να αφαιρέσετε το flash από τη λίστα αναπαραγωγής;',
    ],
    
    // Success Messages
    'msg_removed_from_playlist' => [
        'fi' => 'Poistettu playlistasta',
        'sv' => 'Borttagen från spellistan',
        'en' => 'Removed from playlist',
        'it' => 'Rimosso dalla playlist',
        'el' => 'Αφαιρέθηκε από τη λίστα',
    ],
    'msg_restored_to_playlist' => [
        'fi' => 'Palautettu playlistaan',
        'sv' => 'Återställd till spellistan',
        'en' => 'Restored to playlist',
        'it' => 'Ripristinato nella playlist',
        'el' => 'Επαναφέρθηκε στη λίστα',
    ],
    
    // Log Messages
    'log_display_removed' => [
        'fi' => 'Poistettu infonäyttö-playlistasta',
        'sv' => 'Borttagen från informationsskärmens spellista',
        'en' => 'Removed from info screen playlist',
        'it' => 'Rimosso dalla playlist del display',
        'el' => 'Αφαιρέθηκε από τη λίστα αναπαραγωγής',
    ],
    'log_display_restored' => [
        'fi' => 'Palautettu infonäyttö-playlistaan',
        'sv' => 'Återställd till informationsskärmens spellista',
        'en' => 'Restored to info screen playlist',
        'it' => 'Ripristinato nella playlist del display',
        'el' => 'Επαναφέρθηκε στη λίστα αναπαραγωγής',
    ],
];
