<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Log channel
    |--------------------------------------------------------------------------
    |
    | Name of the Laravel log channel the CMS writes structured audit/observability
    | entries to. Logging is enabled only when this is set (e.g. CMS_LOG_CHANNEL=cms);
    | leave it unset to disable all watch logging at zero per-request cost. If the
    | named channel is not defined in config/logging.php, the watch package registers
    | a daily JSON channel writing to storage/logs/cms.log.
    |
    */
    'channel' => env( 'CMS_LOG_CHANNEL' ),

    /*
    |--------------------------------------------------------------------------
    | Metrics sampling rate
    |--------------------------------------------------------------------------
    |
    | Fraction (0.0 - 1.0) of high-volume read entries (frontend search, JSON:API)
    | that are kept. 1.0 keeps everything; lower it on high-traffic sites. Audit
    | streams (content changes, auth, contact) stay complete regardless of this value.
    |
    */
    'sample' => env( 'CMS_WATCH_SAMPLE', 1.0 ),

    /*
    |--------------------------------------------------------------------------
    | Anonymize personal data
    |--------------------------------------------------------------------------
    |
    | When TRUE (default), personal data (email, IP, user agent) in auth and
    | contact entries is SHA-256 hashed before being logged or recorded. Set to
    | FALSE to store raw values (e.g. for internal, non-EU deployments).
    |
    */
    'anonymize' => env( 'CMS_WATCH_ANONYMIZE', true ),

];
