<?php

return [

    'title' => 'Updates',
    'sub'   => 'Keep your install current. Check for new versions and choose how updates reach you.',

    'demo' => [
        'locked' => 'Updates are disabled in demo mode. On your own install you can check for and apply updates here.',
    ],

    'sections' => [
        'status'      => 'This install',
        'status_sub'  => 'Your current version and when updates were last checked.',
        'prefs'       => 'Preferences',
        'prefs_sub'   => 'Which releases you want and whether to check automatically.',
    ],

    'fields' => [
        'current_version' => 'Current version',
        'last_checked'    => 'Last checked',
        'never_checked'   => 'Never',
        'channel'         => 'Release channel',
        'auto_check'      => 'Check for updates automatically (daily)',
        'auto_install'    => 'Auto-install patch updates (e.g. 1.0.3 → 1.0.4)',
        'pin'             => 'Pin to this version (:version) — stop prompting for updates',
        'pinned_note'     => 'Pinned to v:version. You can still install manually after unpinning.',
    ],

    'channels' => [
        'stable' => 'Stable — recommended',
        'beta'   => 'Beta — try new features early',
    ],

    'banner' => [
        'title'           => 'Update available — v:version',
        'highlights'      => "What's new",
        'breaking'        => 'Breaking changes',
        'size'            => 'Download size',
        'released'        => 'Released',
        'read_notes'      => 'Read full release notes',
        'install'         => 'Install update',
        'skip'            => 'Skip this version',
    ],

    'install' => [
        'title'       => 'Install v',
        'preflight'   => 'Pre-flight checks',
        'checking'    => 'Checking your server…',
        'cannot'      => "This server doesn't meet the requirements for this update. Resolve the items above and try again.",
        'warning'     => 'A backup is taken automatically before the update, and the install rolls back if anything goes wrong.',
        'cancel'      => 'Cancel',
        'install_now' => 'Install now',
        'installing'  => 'Installing update…',
        'do_not'      => 'Do not close or refresh this tab.',
        'done'        => 'Update complete',
        'done_sub'    => 'Reloading to finish up…',
        'reload'      => 'Reload now',
        'failed'      => 'Update failed',
        'close'       => 'Close',
    ],

    'actions' => [
        'check_now' => 'Check for updates',
        'save'      => 'Save changes',
        'history'   => 'Update history',
    ],

    'manual' => [
        'title'         => 'Update manually',
        'sub'           => "No internet to our update server, or you have the new version's .zip from CodeCanyon? Upload it here.",
        'choose_file'   => 'Update package (.zip)',
        'dropzone'      => 'Drop your update .zip here, or click to browse',
        'dropzone_hint' => 'ZIP file only',
        'zip_only'      => 'Please choose a .zip file.',
        'upload'        => 'Upload & check',
        'uploading'     => 'Uploading & checking…',
        'current'       => 'Current version: :version',
    ],

    'history' => [
        'title'      => 'Update history',
        'sub'        => 'Every update attempt and how it ended.',
        'empty'      => 'No updates have been run yet.',
        'col_from'   => 'From',
        'col_to'     => 'To',
        'col_status' => 'Status',
        'col_started' => 'Started',
        'col_finished' => 'Finished',
        'col_channel' => 'Channel',
        'status' => [
            'pending'     => 'Pending',
            'installing'  => 'Installing',
            'success'     => 'Success',
            'failed'      => 'Failed',
            'rolled_back' => 'Rolled back',
        ],
    ],

    'messages' => [
        'up_to_date'      => "You're on the latest version.",
        'available'       => 'A new version is available: v:version.',
        'feed_unreachable' => "Couldn't reach the update server. Your install is unaffected — try again later.",
    ],

    'errors' => [
        'feed_unreachable'   => "Couldn't reach the update server.",
        'no_download_url'    => 'This release has no download link.',
        'download_failed'    => "Couldn't download the update. Check your connection and try again.",
        'checksum_mismatch'  => 'The downloaded update is corrupt (checksum mismatch). Nothing was changed.',
        'no_public_key'      => 'No update signing key is configured, so this update cannot be verified.',
        'sodium_unavailable' => 'This server is missing the Sodium PHP extension, so updates cannot be verified. Ask your host to enable it.',
        'signature_missing'  => 'This update is unsigned and cannot be verified.',
        'signature_invalid'  => "The update's signature is invalid — it may have been tampered with. Nothing was changed.",
        'public_key_invalid' => 'The configured update signing key is invalid.',
        'archive_missing'    => 'The downloaded update could not be found.',
        'archive_unreadable' => 'The downloaded update could not be opened. It may be corrupt.',
        'replace_failed'     => 'Failed to write updated file: :file. The update was rolled back.',
        'preflight_failed'   => "This server doesn't meet the requirements for this update. Review the checks and try again.",
        'no_update'          => 'There is no update available to install.',
        'invalid_package'    => "This file isn't a valid update package (its update.json manifest is missing or unreadable).",
        'not_newer'          => 'This package (v:version) is not newer than your current version (v:current).',
        'session_expired'    => 'This upload expired. Please choose the update file again.',
        'chunk_out_of_order' => 'The upload got out of sync. Please choose the update file and try again.',
        'chunk_write_failed' => 'Could not save the uploaded data on the server. Check that the storage folder is writable.',
    ],

    'preflight' => [
        'php'           => 'PHP :min or newer',
        'php_have'      => 'Running PHP :have',
        'mysql'         => 'MySQL :min or newer',
        'mysql_have'    => 'Running MySQL :have',
        'disk'          => 'Enough free disk space',
        'disk_have'     => ':have free, :need needed',
        'disk_unknown'  => "Couldn't determine free disk space",
        'writable'      => 'Writable: :path',
    ],

    'flash' => [
        'saved'   => 'Update preferences saved.',
        'skipped' => 'Skipped v:version. You\'ll be notified of the next release.',
    ],

];
