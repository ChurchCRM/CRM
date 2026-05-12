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

import Avatar, { type AvatarOptions } from "avatar-initials";
import { buildAPIUrl } from "./api-utils";

interface AvatarInfo {
  hasPhoto: boolean;
  photoUrl: string | null;
  initials: string;
  email: string | null;
}

interface AvatarConfig {
  entityType: "person" | "family";
  entityId: number;
}

// Cache for avatar info to avoid repeated API calls
const avatarInfoCache = new Map<string, AvatarInfo>();

// Helper functions to get Gravatar plugin settings dynamically from page config
// These must be checked at runtime, not at module load time, because window.CRM
// is set up after the bundles are loaded

/**
 * Check if Gravatar is enabled via the Gravatar plugin
 */
function isGravatarEnabled(): boolean {
  // First try the new plugin config structure
  const plugins = window.CRM?.plugins;
  if (plugins?.gravatar?.enabled !== undefined) {
    return plugins.gravatar.enabled;
  }
  // Fall back to legacy config for backward compatibility
  return window.CRM?.bEnableGravatarPhotos ?? false;
}

/**
 * Get the configured Gravatar default image style
 * Gravatar supports: mp, identicon, monsterid, wavatar, retro, robohash, blank
 */
function getGravatarDefaultImage(): string {
  const plugins = window.CRM?.plugins;
  return plugins?.gravatar?.defaultImage ?? "blank";
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
    if (!("IntersectionObserver" in window)) {
      console.warn("IntersectionObserver not supported, falling back to immediate loading");
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
        rootMargin: "50px",
        threshold: 0.01,
      },
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
    const entityType = img.dataset.imageEntityType as "person" | "family" | undefined;
    const entityId = parseInt(img.dataset.imageEntityId || "", 10);

    if (!entityType || !entityId) {
      console.error("Missing required data attributes on image:", img);
      return null;
    }

    if (!["person", "family"].includes(entityType)) {
      console.error("Invalid entity type:", entityType);
      return null;
    }

    return { entityType, entityId };
  }

  /**
   * Get avatar size from CSS class
   */
  private getAvatarSize(img: HTMLImageElement): number {
    if (img.classList.contains("photo-tiny")) return 40;
    if (img.classList.contains("photo-small")) return 85;
    if (img.classList.contains("photo-medium")) return 100;
    if (img.classList.contains("photo-large")) return 200;
    if (img.classList.contains("photo-profile")) {
      // Render at actual displayed size × devicePixelRatio so initials stay crisp
      // on HiDPI screens and on wide cards (e.g. 300px+ family photo). Cap at 600
      // to keep the PNG payload reasonable.
      const rect = img.getBoundingClientRect();
      const dpr = window.devicePixelRatio || 1;
      const target = Math.ceil(Math.max(rect.width, rect.height, 120) * dpr);
      return Math.min(600, target);
    }

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
      return avatarInfoCache.get(cacheKey) as AvatarInfo;
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
    const imageContainer = img.closest(".image-container");
    let viewBtn = imageContainer?.querySelector("#view-larger-image-btn") as HTMLElement;

    // If not found, try looking in parent containers
    if (!viewBtn) {
      const parent = img.closest("[data-image-entity-type]")?.parentElement;
      viewBtn = parent?.querySelector("#view-larger-image-btn") as HTMLElement;
    }

    // Last resort: look for the button on the whole document (shouldn't normally be needed)
    if (!viewBtn) {
      viewBtn = document.querySelector("#view-larger-image-btn") as HTMLElement;
    }

    if (viewBtn) {
      if (hasPhoto) {
        viewBtn.classList.remove("hide-if-no-photo");
        viewBtn.classList.remove("hide"); // Also remove 'hide' for PersonView compatibility
      } else {
        viewBtn.classList.add("hide-if-no-photo");
      }
    }
    // No button found — normal on list/dashboard pages where no view-larger-image button exists

    // Show/hide the inline overlay icon button (inside the photo container)
    const overlay = img.closest(".position-relative")?.querySelector<HTMLElement>(".photo-view-overlay");
    if (overlay) {
      overlay.classList.toggle("d-none", !hasPhoto);
    }
  }

  /**
   * Load avatar for a single image element
   */
  private async loadAvatar(img: HTMLImageElement): Promise<void> {
    // Skip if already loaded
    if (
      img.src &&
      !img.src.includes("data:") &&
      (img.src.includes("/photo") || img.src.includes("/avatar") || img.src.includes("gravatar"))
    ) {
      return;
    }

    const config = this.getAvatarConfig(img);
    if (!config) {
      return;
    }

    const size = this.getAvatarSize(img);

    // Add loading state
    img.classList.add("loading");

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
      console.error("Failed to load avatar:", error);
      img.classList.remove("loading");
      img.classList.add("error");
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
      img.classList.remove("loading");
      img.classList.add("loaded");

      // Only expand to full size for main profile photos (photo-large or photo-profile classes)
      // Keep small circular style for inline/list avatars (photo-small, photo-tiny, photo-medium)
      const isProfilePhoto = img.classList.contains("photo-large") || img.classList.contains("photo-profile");

      if (isProfilePhoto) {
        // For main profile photos, switch to rectangular style (not circular avatar)
        img.classList.remove("photo-large", "photo-medium", "photo-small", "photo-tiny", "photo-profile");
        img.classList.add("img-fluid", "rounded", "uploaded-photo");
        img.style.maxWidth = "100%";
        img.style.maxHeight = "300px";
        img.style.borderRadius = "8px";
        img.style.width = "auto";
        img.style.height = "auto";
      } else {
        // For inline/list photos, keep as circular avatar but use the uploaded photo
        img.classList.add("uploaded-photo");
        img.style.objectFit = "cover";
      }

      // Add click-to-view class so lightbox handlers can detect this photo.
      // Only uploaded photos get this — initials and failed loads do not.
      // Skip images inside upload buttons (profile pages handle their own click).
      if (!img.closest("#uploadImageButton, #uploadImageTrigger")) {
        const viewClass = config.entityType === "person" ? "view-person-photo" : "view-family-photo";
        const dataAttr = config.entityType === "person" ? "data-person-id" : "data-family-id";
        img.classList.add(viewClass);
        img.setAttribute(dataAttr, String(config.entityId));
        img.style.cursor = "pointer";
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
    // Get the configured default image style from the Gravatar plugin
    const defaultImage = getGravatarDefaultImage();

    // If defaultImage is 'blank', we want to fall back to our initials system
    // Otherwise, use Gravatar's server-side fallback images
    const useGravatarFallback = defaultImage !== "blank";

    // Build settings object with all required fields
    const settings: AvatarOptions = {
      useGravatar: true,
      useGravatarFallback: useGravatarFallback, // Use Gravatar's fallback (identicon, monsterid, etc.)
      size: size,
      initials: avatarInfo.initials,
      color: "#ffffff",
      background: generateRandomAvatarColor(),
      fontSize: Math.floor(size * 0.4),
      fontWeight: 600,
      fontFamily: "'Segoe UI', 'Helvetica Neue', Arial, sans-serif",
      fallback: defaultImage, // Gravatar default image type (mp, identicon, monsterid, etc.)
      rating: "g",
      setSourceCallback: () => {
        img.classList.remove("loading");
        img.classList.add("loaded");
        this.cleanupDataAttributes(img);
      },
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
      color: "#ffffff",
      background: generateRandomAvatarColor(),
      fontSize: Math.floor(size * 0.4),
      fontWeight: 600,
      fontFamily: "'Segoe UI', 'Helvetica Neue', Arial, sans-serif",
      setSourceCallback: () => {
        img.classList.remove("loading");
        img.classList.add("loaded");
        this.cleanupDataAttributes(img);
      },
    });
  }

  /**
   * Clean up data attributes after loading
   */
  private cleanupDataAttributes(img: HTMLImageElement): void {
    img.removeAttribute("data-image-entity-type");
    img.removeAttribute("data-image-entity-id");
  }

  /**
   * Load all images immediately (fallback for browsers without IntersectionObserver)
   */
  private loadAllImages(): void {
    const images = document.querySelectorAll<HTMLImageElement>("[data-image-entity-type]");
    images.forEach((img) => {
      this.loadAvatar(img);
    });
  }

  /**
   * Initialize lazy loading for all images with data attributes
   */
  public init(): void {
    const images = document.querySelectorAll<HTMLImageElement>("[data-image-entity-type]");

    if (this.observer) {
      images.forEach((img) => {
        this.observer?.observe(img);
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
      const images = document.querySelectorAll<HTMLImageElement>("[data-image-entity-type]");
      images.forEach((img) => {
        if (!img.src || img.src.includes("data:")) {
          this.observer?.observe(img);
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

// Global delegated click handler for avatar lightbox.
// avatar-loader adds .view-person-photo / .view-family-photo + cursor:pointer
// to uploaded photos. This handler opens the lightbox when clicked.
document.addEventListener("click", (e) => {
  const target = (e.target as HTMLElement).closest<HTMLElement>(".view-person-photo, .view-family-photo");
  if (!target) return;

  e.preventDefault();
  e.stopPropagation();

  const showLightbox = window.CRM?.showPhotoLightbox;
  if (!showLightbox) return;

  if (target.classList.contains("view-person-photo")) {
    const personId = target.dataset.personId;
    if (personId) showLightbox("person", parseInt(personId, 10));
  } else if (target.classList.contains("view-family-photo")) {
    const familyId = target.dataset.familyId;
    if (familyId) showLightbox("family", parseInt(familyId, 10));
  }
});

// Delegated click handler for the inline overlay magnifying-glass button on profile pages.
document.addEventListener("click", (e) => {
  const btn = (e.target as HTMLElement).closest<HTMLElement>(".photo-view-overlay");
  if (!btn) return;

  e.preventDefault();
  e.stopPropagation();

  const showLightbox = window.CRM?.showPhotoLightbox;
  if (!showLightbox) return;

  const type = btn.dataset.entityType;
  const id = btn.dataset.entityId;
  if (type && id) showLightbox(type, parseInt(id, 10));
});

// Auto-initialize when DOM is ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    avatarLoader.init();
  });
} else {
  avatarLoader.init();
}

// Export for manual usage
export default avatarLoader;

// Also attach to window for legacy code
window.CRM = window.CRM || {};
window.CRM.avatarLoader = avatarLoader;
// Keep backward compatibility with old name
window.CRM.peopleImageLoader = avatarLoader;
