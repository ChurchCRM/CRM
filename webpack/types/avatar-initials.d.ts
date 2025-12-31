/**
 * Type declarations for avatar-initials npm package
 * @see https://www.npmjs.com/package/avatar-initials
 */

declare module 'avatar-initials' {
    interface AvatarOptions {
        // Primary source
        primarySource?: string;
        fallbackImage?: string;
        size?: number;
        setSourceCallback?: () => void;

        // Initial avatars
        initials?: string;
        color?: string;
        background?: string;
        fontSize?: number;
        fontWeight?: number;
        fontFamily?: string;
        offsetX?: number;
        offsetY?: number;
        width?: number;
        height?: number;

        // Gravatar
        useGravatar?: boolean;
        useGravatarFallback?: boolean;
        hash?: string;
        email?: string;
        fallback?: string;
        rating?: string;
        forcedefault?: boolean;

        // GitHub
        githubId?: string | number;
    }

    class Avatar {
        constructor(element: HTMLImageElement, options?: AvatarOptions);
        
        static from(element: HTMLImageElement, options?: AvatarOptions): Avatar;
        
        static githubAvatar(options: { id: string | number }): string;
        
        static gravatarUrl(options: { email?: string; hash?: string }): string;
        
        static initialAvatar(options: {
            initials: string;
            initial_fg?: string;
            initial_bg?: string;
            initial_size?: number;
            initial_weight?: number;
            initial_font_family?: string;
        }): string;
    }

    export default Avatar;
    export { Avatar, AvatarOptions };
}
