/**
 * Kiosk Webpack Entry Point
 * 
 * Bundles the kiosk JSOM (JavaScript Object Model) and event handlers
 * for the Sunday School check-in kiosk functionality.
 */

// Import and initialize the kiosk module
import './kiosk-jsom';

// Import event handlers (auto-registers on import)
import './kiosk-events';

// Export for potential external use
export { kiosk } from './kiosk-jsom';
