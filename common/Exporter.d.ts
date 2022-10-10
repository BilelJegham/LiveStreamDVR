export interface ExporterOptions {
    vod?: string;
    directory?: string;
    host?: string;
    username?: string;
    password?: string;
    description?: string;
    tags?: string;
    category?: string;
    privacy?: "public" | "private" | "unlisted";
    file_folder?: string;
    file_name?: string;
    file_source?: "segment" | "downloaded" | "burned";
    title_template?: string;
    title?: string;
    remote?: string;
    playlist_id?: string;
}