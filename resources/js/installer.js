import Alpine from 'alpinejs';
import { enhancedSelect } from './admin/select.js';
import { migrateRunner } from './installer/migrate-runner.js';
import { installerDbForm } from './installer/db-form.js';

// Searchable <select> upgrade (TomSelect), shared with the admin so the
// installer's country / currency / timezone pickers look + behave identically
// to the rest of the system. Registered before Alpine.start().
Alpine.data('enhancedSelect', enhancedSelect);

// Chunked migrate + seed progress driver for the database step.
Alpine.data('migrateRunner', migrateRunner);

// AJAX submit for the database credentials step (no page reload on a
// failed connection test).
Alpine.data('installerDbForm', installerDbForm);

window.Alpine = Alpine;
Alpine.start();
