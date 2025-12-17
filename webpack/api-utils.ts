/**
 * API Utilities for Webpack TypeScript modules
 * 
 * Provides consistent API URL construction and fetch wrappers for webpack bundles.
 * This ensures all webpack modules properly use window.CRM.root for base URL,
 * even when window.CRM might not be available at module load time.
 */

/**
 * Get the CRM root path dynamically - ensures window.CRM is available
 * 
 * Note: Must be called at runtime, not at module load time, because window.CRM
 * is initialized AFTER webpack bundles are loaded.
 * 
 * @returns The root path (e.g., '', '/churchcrm', '/crm')
 */
export function getRootPath(): string {
    return (window as any).CRM?.root || '';
}

/**
 * Build a complete API URL with root path prepended
 * 
 * @param path - The API path (e.g., 'person/123/avatar', 'family/456/photo')
 * @returns The complete URL (e.g., '/churchcrm/api/person/123/avatar')
 */
export function buildAPIUrl(path: string): string {
    return `${getRootPath()}/api/${path}`;
}

/**
 * Build a complete Admin API URL with root path prepended
 * 
 * @param path - The Admin API path (e.g., 'system/config/setting-name')
 * @returns The complete URL (e.g., '/churchcrm/admin/api/system/config/setting-name')
 */
export function buildAdminAPIUrl(path: string): string {
    return `${getRootPath()}/admin/api/${path}`;
}

/**
 * Fetch wrapper for API calls with automatic error logging
 * 
 * @param path - The API path (relative to /api/, e.g., 'person/123/avatar')
 * @param options - Fetch options (method, headers, body, etc.)
 * @returns Promise resolving to the Response object
 * @throws Error if the fetch fails or response is not ok
 */
export async function fetchAPI(
    path: string,
    options: RequestInit = {}
): Promise<Response> {
    const url = buildAPIUrl(path);
    
    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers,
            },
            ...options,
        });

        if (!response.ok) {
            throw new Error(`API request failed: ${response.status} ${response.statusText}`);
        }

        return response;
    } catch (error) {
        console.error(`Failed to fetch ${url}:`, error);
        throw error;
    }
}

/**
 * Fetch wrapper for Admin API calls
 * 
 * @param path - The Admin API path (relative to /admin/api/, e.g., 'system/config/setting-name')
 * @param options - Fetch options (method, headers, body, etc.)
 * @returns Promise resolving to the Response object
 */
export async function fetchAdminAPI(
    path: string,
    options: RequestInit = {}
): Promise<Response> {
    const url = buildAdminAPIUrl(path);
    
    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers,
            },
            ...options,
        });

        if (!response.ok) {
            throw new Error(`Admin API request failed: ${response.status} ${response.statusText}`);
        }

        return response;
    } catch (error) {
        console.error(`Failed to fetch ${url}:`, error);
        throw error;
    }
}

/**
 * Helper to get JSON response from API call
 * 
 * @param path - The API path
 * @param options - Fetch options
 * @returns Promise resolving to parsed JSON response
 */
export async function fetchAPIJSON<T = any>(
    path: string,
    options: RequestInit = {}
): Promise<T> {
    const response = await fetchAPI(path, options);
    return response.json();
}

/**
 * Helper to get JSON response from Admin API call
 * 
 * @param path - The Admin API path
 * @param options - Fetch options
 * @returns Promise resolving to parsed JSON response
 */
export async function fetchAdminAPIJSON<T = any>(
    path: string,
    options: RequestInit = {}
): Promise<T> {
    const response = await fetchAdminAPI(path, options);
    return response.json();
}
