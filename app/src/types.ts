export type Mood = 1 | 2 | 3 | 4 | 5;

export interface Weather {
	weather_code: number;
	temp_max: number | null;
	temp_min: number | null;
	label: string;
	source: string;
}

export interface Photo {
	id: number;
	width: number;
	height: number;
	taken_at: string;
	thumb: string;
	medium: string;
	full: string;
}

export interface Card {
	date: string;
	mood: Mood | null;
	chars: number;
	excerpt: string;
	tags: string[];
	photos: Photo[];
}

export interface Page<T> {
	items: T[];
	next_cursor: string | null;
}

export interface SearchItem {
	date: string;
	mood: Mood | null;
	segments: [ string, boolean ][];
}

export interface MonthDay {
	mood: Mood | null;
	level: number;
	chars: number;
	photos: number;
}

export interface Entry {
	date: string;
	exists: boolean;
	content: string;
	mood: Mood | null;
	tags: string[];
	weather: Weather | null;
	location: { lat: number; lng: number; label: string } | null;
	char_count: number;
	photos: Photo[];
	version: number;
	updated_at: string | null;
}

export interface Settings {
	day_start_hour: number;
	reminder_enabled: boolean;
	reminder_time: string;
	weather_enabled: boolean;
	weather_lat: number | null;
	weather_lng: number | null;
	weather_place: string;
	location_enabled: boolean;
	prompts_enabled: boolean;
	font_size: 'S' | 'M' | 'L';
	font_family: 'sans' | 'serif';
	color_theme: 'auto' | 'light' | 'dark';
}

export interface Stats {
	current_streak: number;
	longest_streak: number;
	total_entries: number;
	total_chars: number;
	month_entries: number;
	written_today: boolean;
}

export interface Bootstrap {
	user: { id: number; display_name: string };
	settings: Settings;
	today: string;
	timezone: string;
	nonce: string;
	stats: Stats;
	entry: Entry;
	api: {
		root: string;
		nonce_url: string;
		login_url: string;
		home_path: string;
	};
	push: { public_key: string };
}

export interface EntryFields {
	content?: string;
	mood?: Mood | null;
	tags?: string[];
	weather?: Weather | null;
}

export interface Place {
	name: string;
	admin: string;
	country: string;
	lat: number;
	lng: number;
}
