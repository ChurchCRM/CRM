/**
 * ChurchCRM Avatar Loader
 * 
 * Loads person and family avatars using the avatar-initials library.
 * Priority: Uploaded Photo > Gravatar (if enabled) > Generated Initials
 * 
 * Usage in HTML:
 * <img 
 *   data-image-entity-type="person" 
 *   data-image-entity-id="123" 
 *   class="photo-small">
 * 
 * Or for families:
 * <img 
 *   data-image-entity-type="family" 
 *   data-image-entity-id="456" 
 *   class="photo-large">
 * 
 * CSS classes control the display size:
 * - photo-tiny: 40px (PersonView family members)
 * - photo-small: 85px (maps, lists)
 * - photo-medium: 100px (standard cards)
 * - photo-large: 200px (main family photo)
 * - photo-profile: 200px (profile pages)
 */

import Avatar from 'avatar-initials';
import { buildAPIUrl } from './api-utils';

interface AvatarInfo {
    hasPhoto: boolean;
    photoUrl: string | null;
    initials: string;
    email: string | null;
}

interface AvatarConfig {
    entityType: 'person' | 'family';
    entityId: number;
}

// Cache for avatar info to avoid repeated API calls
const avatarInfoCache = new Map<string, AvatarInfo>();

// Helper function to get gravatar setting dynamically from page config
// This must be checked at runtime, not at module load time, because window.CRM
// is set up after the bundles are loaded
function isGravatarEnabled(): boolean {
    return (window as any).CRM?.bEnableGravatarPhotos ?? false;
}

/**
 * Generate a random HSL color with good saturation and brightness
 * for visible initials/text. Generates truly random colors.
 */
function generateRandomAvatarColor(): string {
    // Random hue between 0-360
    const hue = Math.floor(Math.random() * 360);
    // Fixed saturation and lightness for consistent visibility
    const saturation = 65;
    const lightness = 45;
    return `hsl(${hue}, ${saturation}%, ${lightness}%)`;
}

class AvatarLoader {
    private observer: IntersectionObserver | null = null;
    
    constructor() {
        this.initializeObserver();
    }
    
    /**
     * Initialize the Intersection Observer for lazy loading
     */
    private initializeObserver(): void {
        if (!('IntersectionObserver' in window)) {
            console.warn('IntersectionObserver not supported, falling back to immediate loading');
            this.loadAllImages();
            return;
        }
        
        this.observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const img = entry.target as HTMLImageElement;
                        this.loadAvatar(img);
                        this.observer?.unobserve(img);
                    }
                });
            },
            {
                rootMargin: '50px',
                threshold: 0.01
            }
        );
    }
    
    /**
     * Build the API URL for avatar info
     */
    private buildAvatarInfoUrl(config: AvatarConfig): string {
        const { entityType, entityId } = config;
        return buildAPIUrl(`${entityType}/${entityId}/avatar`);
    }
    
    /**
     * Build the API URL for the actual photo (uploaded photos only)
     */
    private buildPhotoUrl(config: AvatarConfig): string {
        const { entityType, entityId } = config;
        return buildAPIUrl(`${entityType}/${entityId}/photo`);
    }
    
    /**
     * Extract configuration from image element data attributes
     */
    private getAvatarConfig(img: HTMLImageElement): AvatarConfig | null {
        const entityType = img.dataset.imageEntityType as 'person' | 'family' | undefined;
        const entityId = parseInt(img.dataset.imageEntityId || '', 10);
        
        if (!entityType || !entityId) {
            console.error('Missing required data attributes on image:', img);
            return null;
        }
        
        if (!['person', 'family'].includes(entityType)) {
            console.error('Invalid entity type:', entityType);
            return null;
        }
        
        return { entityType, entityId };
    }
    
    /**
     * Get avatar size from CSS class
     */
    private getAvatarSize(img: HTMLImageElement): number {
        if (img.classList.contains('photo-tiny')) return 40;
        if (img.classList.contains('photo-small')) return 85;
        if (img.classList.contains('photo-medium')) return 100;
        if (img.classList.contains('photo-large')) return 200;
        if (img.classList.contains('photo-profile')) return 200;
        
        // Default size
        return 100;
    }
    
    /**
     * Fetch avatar info from API
     */
    private async fetchAvatarInfo(config: AvatarConfig): Promise<AvatarInfo> {
        const cacheKey = `${config.entityType}-${config.entityId}`;
        
        // Check cache first
        if (avatarInfoCache.has(cacheKey)) {
            return avatarInfoCache.get(cacheKey)!;
        }
        
        const url = this.buildAvatarInfoUrl(config);
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`Failed to fetch avatar info: ${response.status}`);
        }
        
        const info: AvatarInfo = await response.json();
        
        // Cache the result
        avatarInfoCache.set(cacheKey, info);
        
        return info;
    }
    
    /**
     * Show or hide the view photo button based on whether a photo exists
     */
    private updateViewPhotoButton(img: HTMLImageElement, hasPhoto: boolean): void {
        // Try to find the button - look up to the image-container's parent and search for the button
        let imageContainer = img.closest('.image-container');
        let viewBtn = imageContainer?.querySelector('#view-larger-image-btn') as HTMLElement;
        
        // If not found, try looking in parent containers
        if (!viewBtn) {
            const parent = img.closest('[data-image-entity-type]')?.parentElement;
            viewBtn = parent?.querySelector('#view-larger-image-btn') as HTMLElement;
        }
        
        // Last resort: look for the button on the whole document (shouldn't normally be needed)
        if (!viewBtn) {
            viewBtn = document.querySelector('#view-larger-image-btn') as HTMLElement;
        }
        
        if (viewBtn) {
            if (hasPhoto) {
                viewBtn.classList.remove('hide-if-no-photo');
                viewBtn.classList.remove('hide'); // Also remove 'hide' for PersonView compatibility
            } else {
                viewBtn.classList.add('hide-if-no-photo');
            }
        } else {
            console.warn('Could not find view-larger-image-btn button for entity', img.dataset);
        }
    }

    /**
     * Load avatar for a single image element
     */
    private async loadAvatar(img: HTMLImageElement): Promise<void> {
        // Skip if already loaded
        if (img.src && !img.src.includes('data:') && (img.src.includes('/photo') || img.src.includes('/avatar') || img.src.includes('gravatar'))) {
            return;
        }
        
        const config = this.getAvatarConfig(img);
        if (!config) {
            return;
        }
        
        const size = this.getAvatarSize(img);
        
        // Add loading state
        img.classList.add('loading');
        
        try {
            const avatarInfo = await this.fetchAvatarInfo(config);
            
            if (avatarInfo.hasPhoto) {
                // Has uploaded photo - load it directly
                this.updateViewPhotoButton(img, true);
                this.loadUploadedPhoto(img, config, avatarInfo);
            } else if (isGravatarEnabled() && avatarInfo.email) {
                // Try Gravatar with initials fallback - hide view button (no uploaded photo)
                this.updateViewPhotoButton(img, false);
                this.loadWithGravatar(img, avatarInfo, size);
            } else {
                // Just use initials - hide view button
                this.updateViewPhotoButton(img, false);
                this.loadInitials(img, avatarInfo, size);
            }
        } catch (error) {
            console.error('Failed to load avatar:', error);
            img.classList.remove('loading');
            img.classList.add('error');
            this.updateViewPhotoButton(img, false);
        }
    }
    
    /**
     * Load an uploaded photo
     */
    private loadUploadedPhoto(img: HTMLImageElement, config: AvatarConfig, avatarInfo: AvatarInfo): void {
        const photoUrl = this.buildPhotoUrl(config);
        
        // Load the image directly - browser will send authentication cookies automatically
        img.onload = () => {
            img.classList.remove('loading');
            img.classList.add('loaded');
            
            // Only expand to full size for main profile photos (photo-large or photo-profile classes)
            // Keep small circular style for inline/list avatars (photo-small, photo-tiny, photo-medium)
            const isProfilePhoto = img.classList.contains('photo-large') || img.classList.contains('photo-profile');
            
            if (isProfilePhoto) {
                // For main profile photos, switch to rectangular style (not circular avatar)
                img.classList.remove('photo-large', 'photo-medium', 'photo-small', 'photo-tiny', 'photo-profile');
                img.classList.add('img-fluid', 'rounded', 'uploaded-photo');
                img.style.maxWidth = '100%';
                img.style.maxHeight = '300px';
                img.style.borderRadius = '8px';
                img.style.width = 'auto';
                img.style.height = 'auto';
            } else {
                // For inline/list photos, keep as circular avatar but use the uploaded photo
                img.classList.add('uploaded-photo');
                img.style.objectFit = 'cover';
            }
            
            this.cleanupDataAttributes(img);
        };
        
        img.onerror = () => {
            // Photo failed to load, fall back to initials
            console.warn(`Uploaded photo failed to load from ${photoUrl}, falling back to initials`);
            this.loadInitials(img, avatarInfo, this.getAvatarSize(img));
        };
        
        // Set src to trigger load
        img.src = photoUrl;
    }
    
    /**
     * Load avatar with Gravatar (with initials fallback)
     */
    private loadWithGravatar(img: HTMLImageElement, avatarInfo: AvatarInfo, size: number): void {
        // Build settings object with all required fields
        const settings: any = {
            useGravatar: true,
            useGravatarFallback: false,
            size: size,
            initials: avatarInfo.initials,
            color: '#ffffff',
            background: generateRandomAvatarColor(),
            fontSize: Math.floor(size * 0.4),
            fontWeight: 600,
            fontFamily: "'Segoe UI', 'Helvetica Neue', Arial, sans-serif",
            fallback: 'blank', // Use blank fallback to trigger initials
            rating: 'g',
            setSourceCallback: () => {
                img.classList.remove('loading');
                img.classList.add('loaded');
                this.cleanupDataAttributes(img);
            }
        };
        
        // Only set email if it exists - if not set, avatar library will use initials as fallback
        if (avatarInfo.email) {
            settings.email = avatarInfo.email;
        }
        
        Avatar.from(img, settings);
    }
    
    /**
     * Load avatar with initials only (no Gravatar)
     */
    private loadInitials(img: HTMLImageElement, avatarInfo: AvatarInfo, size: number): void {
        // Use avatar-initials library for initials
        Avatar.from(img, {
            useGravatar: false,
            initials: avatarInfo.initials,
            size: size,
            color: '#ffffff',
            background: generateRandomAvatarColor(),
            fontSize: Math.floor(size * 0.4),
            fontWeight: 600,
            fontFamily: "'Segoe UI', 'Helvetica Neue', Arial, sans-serif",
            setSourceCallback: () => {
                img.classList.remove('loading');
                img.classList.add('loaded');
                this.cleanupDataAttributes(img);
            }
        });
    }
    
    /**
     * Clean up data attributes after loading
     */
    private cleanupDataAttributes(img: HTMLImageElement): void {
        img.removeAttribute('data-image-entity-type');
        img.removeAttribute('data-image-entity-id');
    }
    
    /**
     * Load all images immediately (fallback for browsers without IntersectionObserver)
     */
    private loadAllImages(): void {
        const images = document.querySelectorAll<HTMLImageElement>('[data-image-entity-type]');
        images.forEach((img) => this.loadAvatar(img));
    }
    
    /**
     * Initialize lazy loading for all images with data attributes
     */
    public init(): void {
        const images = document.querySelectorAll<HTMLImageElement>('[data-image-entity-type]');
        
        if (this.observer) {
            images.forEach((img) => {
                this.observer!.observe(img);
            });
        } else {
            this.loadAllImages();
        }
    }
    
    /**
     * Manually load a specific image
     */
    public loadImageById(elementId: string): void {
        const img = document.getElementById(elementId) as HTMLImageElement;
        if (img) {
            this.loadAvatar(img);
        }
    }
    
    /**
     * Refresh observer for dynamically added content
     */
    public refresh(): void {
        // Clear cache for fresh data
        avatarInfoCache.clear();
        
        if (this.observer) {
            const images = document.querySelectorAll<HTMLImageElement>('[data-image-entity-type]');
            images.forEach((img) => {
                if (!img.src || img.src.includes('data:')) {
                    this.observer!.observe(img);
                }
            });
        } else {
            this.loadAllImages();
        }
    }
    
    /**
     * Clear the avatar info cache
     */
    public clearCache(): void {
        avatarInfoCache.clear();
    }
    
    /**
     * Destroy the observer (cleanup)
     */
    public destroy(): void {
        if (this.observer) {
            this.observer.disconnect();
            this.observer = null;
        }
    }
}

// Create singleton instance
const avatarLoader = new AvatarLoader();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        avatarLoader.init();
    });
} else {
    avatarLoader.init();
}

// Export for manual usage
export default avatarLoader;

// Also attach to window for legacy code
(window as any).CRM = (window as any).CRM || {};
(window as any).CRM.avatarLoader = avatarLoader;
// Keep backward compatibility with old name
(window as any).CRM.peopleImageLoader = avatarLoader;
