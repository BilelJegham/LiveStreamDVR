import type { Providers } from "./Defs";

export type VideoQuality = "best" | "1080p60" | "1080p" | "720p60" | "720p" | "480p" | "360p" | "160p" | "140p" | "worst" | "audio_only";
export interface SettingField<T> {
    key: string;
    group: string;
    text: string;

    /**
     * Value type
     */
    type: "string" | "number" | "boolean" | "array" | "template" | "text";
    // type: T;

    /** Default value */
    default?: T;

    /** Array of choices */
    choices?: T[];

    /** Help text to appear next to field in settings */
    help?: string;

    /** Required to save settings? */
    required?: boolean;

    /** Automatically strip slashes? */
    stripslash?: boolean;

    /** Send to client? */
    secret?: boolean;

    deprecated?: boolean | string;

    pattern?: string;

    restart_required?: boolean;

    replacements?: Record<string, {
        display: string;
    }>;
    context?: string;

    guest?: boolean;

}

export interface BaseChannelConfig {
    provider: Providers;
    quality: VideoQuality[];
    match: string[];
    download_chat: boolean;
    burn_chat: boolean;
    no_capture: boolean;
    live_chat: boolean;
    no_cleanup: boolean;
    max_storage: number;
    max_vods: number;
    download_vod_at_end: boolean;
    download_vod_at_end_quality: VideoQuality;
}

export interface TwitchChannelConfig extends BaseChannelConfig {
    provider: "twitch";
    login: string;
}

export interface YouTubeChannelConfig extends BaseChannelConfig {
    provider: "youtube";
    channel_id: string;
}

export type ChannelConfig = TwitchChannelConfig | YouTubeChannelConfig;