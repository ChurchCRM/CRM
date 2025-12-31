/**
 * Dynamic Locale Loader
 * Loads locale files on-demand for moment, FullCalendar, Bootstrap DatePicker, and Select2
 */

window.CRM = window.CRM || {};

/**
 * Dynamically load a script file
 * @param {string} url - The URL of the script to load
 * @returns {Promise<void>}
 */
function loadScript(url) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = url;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`Failed to load script: ${url}`));
        document.head.appendChild(script);
    });
}

/**
 * Load all locale files for the specified locale
 * @param {object} localeConfig - Locale configuration from locales.json
 */
async function loadLocaleFiles(localeConfig) {
    // Validate localeConfig exists
    if (!localeConfig) {
        console.warn('localeConfig is null or undefined, skipping locale loading');
        window.CRM.i18keys = {};
        window.CRM.localesLoaded = true;
        window.dispatchEvent(new Event('CRM.localesReady'));
        return;
    }

    const rootPath = window.CRM.root || '';
    const promises = [];

    try {
        // Load i18n translation keys from JSON
        // Skip loading for en_US as it's the base language (no translation file needed)
        if (localeConfig.locale && localeConfig.locale !== 'en_US') {
            const i18nPath = `${rootPath}/locale/i18n/${localeConfig.locale}.json`;
            try {
                const response = await fetch(i18nPath);
                if (response.ok) {
                    window.CRM.i18keys = await response.json();
                } else {
                    console.warn(`i18n keys not found for locale: ${localeConfig.locale}, using empty object`);
                    window.CRM.i18keys = {};
                }
            } catch (e) {
                console.warn(`Failed to load i18n keys for ${localeConfig.locale}:`, e);
                window.CRM.i18keys = {};
            }
        } else {
            // en_US uses empty object (base language, no translations needed)
            window.CRM.i18keys = {};
        }

        // Load Moment.js locale if configured
        // Skip for 'en' as it's the default locale built into moment.js
        if (localeConfig.momentLocale && localeConfig.momentLocale !== 'en' && typeof moment !== 'undefined') {
            const momentPath = `${rootPath}/locale/vendor/moment/${localeConfig.momentLocale}.js`;
            promises.push(
                loadScript(momentPath)
                    .then(() => {
                        if (typeof moment !== 'undefined' && typeof moment.locale === 'function') {
                            moment.locale(localeConfig.momentLocale);
                            console.log(`Loaded moment locale: ${localeConfig.momentLocale}`);
                        }
                    })
                    .catch(e => console.warn(`Failed to load moment locale ${localeConfig.momentLocale}:`, e))
            );
        } else if (localeConfig.momentLocale === 'en' && typeof moment !== 'undefined') {
            // Set to 'en' without loading (built-in default)
            moment.locale('en');
            console.log(`Using built-in moment locale: en`);
        }

        // Load Bootstrap DatePicker locale if configured
        if (localeConfig.datePicker) {
            const dpPath = `${rootPath}/locale/vendor/bootstrap-datepicker/bootstrap-datepicker.${localeConfig.languageCode}.min.js`;
            promises.push(
                loadScript(dpPath)
                    .then(() => console.log(`Loaded DatePicker locale: ${localeConfig.languageCode}`))
                    .catch(e => console.warn(`Failed to load DatePicker locale ${localeConfig.languageCode}:`, e))
            );
        }

        // Load Select2 i18n if configured
        if (localeConfig.select2) {
            const s2Path = `${rootPath}/locale/vendor/select2/${localeConfig.languageCode}.js`;
            promises.push(
                loadScript(s2Path)
                    .then(() => console.log(`Loaded Select2 locale: ${localeConfig.languageCode}`))
                    .catch(e => console.warn(`Failed to load Select2 locale ${localeConfig.languageCode}:`, e))
            );
        }

        // Load FullCalendar locale if configured
        if (localeConfig.fullCalendar) {
            let fcLocale = localeConfig.languageCode.toLowerCase();
            if (localeConfig.fullCalendarLocale) {
                fcLocale = localeConfig.fullCalendarLocale;
            }
            const fcPath = `${rootPath}/locale/vendor/fullcalendar/${fcLocale}.js`;
            promises.push(
                loadScript(fcPath)
                    .then(() => console.log(`Loaded FullCalendar locale: ${fcLocale}`))
                    .catch(e => console.warn(`Failed to load FullCalendar locale ${fcLocale}:`, e))
            );
        }

        // Wait for all locale files to load
        await Promise.all(promises);
        console.log(`All locale files loaded for: ${localeConfig.locale}`);
        
        // Initialize i18next after locale keys are loaded
        if (typeof i18next !== 'undefined' && window.CRM.i18keys) {
            const i18nextOpt = {
                lng: window.CRM.shortLocale,
                nsSeparator: false,
                keySeparator: false,
                pluralSeparator: false,
                contextSeparator: false,
                fallbackLng: false,
                resources: {}
            };
            i18nextOpt.resources[window.CRM.shortLocale] = {
                translation: window.CRM.i18keys
            };
            i18next.init(i18nextOpt);
            console.log('i18next initialized with locale keys');
            
            // Dispatch event to signal that locales are ready
            window.dispatchEvent(new Event('CRM.localesReady'));
            window.CRM.localesLoaded = true;
        }
    } catch (error) {
        console.error('Error loading locale files:', error);
        // Even on error, mark as loaded to prevent infinite waiting
        window.CRM.localesLoaded = true;
        window.dispatchEvent(new Event('CRM.localesReady'));
    }
}

// Export for use in other scripts
window.CRM.loadLocaleFiles = loadLocaleFiles;
