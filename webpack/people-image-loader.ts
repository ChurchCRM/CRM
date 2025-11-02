/**
 * ChurchCRM People Image Lazy Loader
 * 
 * Loads person and family photos on-demand using data attributes.
 * All images are loaded at full resolution and sized via CSS classes.
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
 */

interface ImageConfig {
    entityType: 'person' | 'family';
    entityId: number;
}

class PeopleImageLoader {
    private observer: IntersectionObserver | null = null;
    private rootPath: string;
    
    constructor() {
        this.rootPath = (window as any).CRM?.root || '';
        this.initializeObserver();
    }
    
    /**
     * Initialize the Intersection Observer for lazy loading
     */
    private initializeObserver(): void {
        // Check if IntersectionObserver is supported
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
                        this.loadImage(img);
                        this.observer?.unobserve(img);
                    }
                });
            },
            {
                rootMargin: '50px', // Start loading 50px before the image enters viewport
                threshold: 0.01
            }
        );
    }
    
    /**
     * Build the API URL for the image
     * Always fetches the full-resolution photo; CSS handles sizing
     */
    private buildImageUrl(config: ImageConfig): string {
        const { entityType, entityId } = config;
        return `${this.rootPath}/api/${entityType}/${entityId}/photo`;
    }
    
    /**
     * Extract configuration from image element data attributes
     */
    private getImageConfig(img: HTMLImageElement): ImageConfig | null {
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
     * Load a single image
     */
    private loadImage(img: HTMLImageElement): void {
        // Skip if already loaded - check if it has a real image src (not page URL or data URI placeholder)
        if (img.src && !img.src.includes('data:') && (img.src.includes('/photo') || img.src.match(/\.(jpg|jpeg|png|gif|webp)$/i))) {
            return;
        }
        
        const config = this.getImageConfig(img);
        if (!config) {
            return;
        }
        
        const imageUrl = this.buildImageUrl(config);
        
        // Add loading state
        img.classList.add('loading');
        
        // Create a temporary image to test loading
        const tempImg = new Image();
        
        tempImg.onload = () => {
            img.src = imageUrl;
            img.classList.remove('loading');
            img.classList.add('loaded');
            img.removeAttribute('data-image-entity-type');
            img.removeAttribute('data-image-entity-id');
        };
        
        tempImg.onerror = () => {
            console.error('Failed to load image:', imageUrl);
            img.classList.remove('loading');
            img.classList.add('error');
            // Optionally set a fallback image
            // img.src = this.rootPath + '/images/placeholder.png';
        };
        
        tempImg.src = imageUrl;
    }
    
    /**
     * Load all images immediately (fallback for browsers without IntersectionObserver)
     */
    private loadAllImages(): void {
        const images = document.querySelectorAll<HTMLImageElement>('[data-image-entity-type]');
        images.forEach((img) => this.loadImage(img));
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
            // Fallback: load all images immediately
            this.loadAllImages();
        }
    }
    
    /**
     * Manually load a specific image
     */
    public loadImageById(elementId: string): void {
        const img = document.getElementById(elementId) as HTMLImageElement;
        if (img) {
            this.loadImage(img);
        }
    }
    
    /**
     * Refresh observer for dynamically added content
     */
    public refresh(): void {
        if (this.observer) {
            const images = document.querySelectorAll<HTMLImageElement>('[data-image-entity-type]');
            images.forEach((img) => {
                // Only observe images that haven't been loaded yet
                if (!img.src || img.src.includes('data:')) {
                    this.observer!.observe(img);
                }
            });
        } else {
            this.loadAllImages();
        }
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
const peopleImageLoader = new PeopleImageLoader();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        peopleImageLoader.init();
    });
} else {
    // DOM is already loaded
    peopleImageLoader.init();
}

// Export for manual usage
export default peopleImageLoader;

// Also attach to window for legacy code
(window as any).CRM = (window as any).CRM || {};
(window as any).CRM.peopleImageLoader = peopleImageLoader;
