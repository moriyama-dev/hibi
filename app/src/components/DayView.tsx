import { useEffect, useMemo, useRef, useState } from 'preact/hooks';
import { api } from '../api';
import { dayLabel, shiftDate, timeStamp, yearLabel } from '../date';
import { beforeNavigate, dayHref, navigate } from '../router';
import { EntrySaver } from '../saver';
import { drafts, type Draft } from '../drafts';
import { boot, entryCache, settings, stats, today } from '../store';
import { openDates, refreshDraftState } from '../sync';
import type { Entry, Mood, Photo, Weather } from '../types';
import { ConflictBanner } from './ConflictBanner';
import { OnThisDayCard } from './OnThisDay';
import { PhotoButton, PhotoStrip } from './Photos';
import { ReadView } from './ReadView';
import { SaveStatus } from './SaveStatus';
import { TagEditor } from './TagEditor';

const MOODS: { value: Mood; label: string }[] = [
	{ value: 1, label: 'とても悪い' },
	{ value: 2, label: '悪い' },
	{ value: 3, label: 'ふつう' },
	{ value: 4, label: '良い' },
	{ value: 5, label: '最高' },
];

const finePointer = window.matchMedia( '(pointer: fine)' ).matches;

interface Props {
	date: string;
	isToday: boolean;
}

export function DayView( { date, isToday }: Props ) {
	const [ entry, setEntry ] = useState<Entry | null>( null );
	const [ draft, setDraft ] = useState<Draft | undefined>( undefined );
	const [ loadError, setLoadError ] = useState( false );
	// Past days open for reading, so leafing through cannot change them by accident.
	const [ editing, setEditing ] = useState( isToday );

	useEffect( () => {
		let alive = true;
		setEntry( null );
		setLoadError( false );
		setEditing( isToday );

		// The draft lookup takes a few milliseconds; waiting for it means the
		// editor never shows older text and then swaps it out.
		const cached = entryCache.get( date );
		const remote = cached ? Promise.resolve( cached ) : api.getEntry( date ).then( ( e ) => ( entryCache.set( date, e ), e ) );
		Promise.all( [ drafts.get( date ), remote.catch( () => null ) ] ).then( ( [ d, e ] ) => {
			if ( ! alive ) {
				return;
			}
			setDraft( d );
			if ( d ) {
				setEditing( true );
			}
			if ( e ) {
				setEntry( e );
			} else if ( d ) {
				setEntry( blankEntry( date, d.base_version ) );
			} else {
				setLoadError( true );
			}
		} );
		return () => {
			alive = false;
		};
	}, [ date ] );

	const writeOffline = () => {
		setLoadError( false );
		setEditing( true );
		setEntry( blankEntry( date, 0 ) );
	};

	return (
		<div class="day">
			<DayHeader date={ date } isToday={ isToday } />
			{ entry && editing && <Editor key={ date } entry={ entry } draft={ draft } isToday={ isToday } /> }
			{ entry && ! editing && (
				<div class="reading">
					{ entry.weather && <WeatherChip weather={ entry.weather } /> }
					{ entry.mood && <p class="read-mood">気分：{ MOODS[ entry.mood - 1 ].label }</p> }
					<ReadView entry={ entry } />
					<PhotoStrip date={ date } photos={ entry.photos } editable={ false } onChange={ () => undefined } />
					{ entry.tags.length > 0 && <p class="read-tags">{ entry.tags.map( ( t ) => `#${ t }` ).join( ' ' ) }</p> }
					<button type="button" class="edit-button" onClick={ () => setEditing( true ) }>
						{ entry.exists ? '編集する' : 'この日のことを書く' }
					</button>
				</div>
			) }
			{ ! entry && ! loadError && (
				<p class="day-loading" role="status">
					読み込み中…
				</p>
			) }
			{ loadError && (
				<div class="day-loading" role="status">
					<p>この日の日記を読み込めませんでした。オフラインかもしれません。</p>
					<button type="button" class="edit-button" onClick={ writeOffline }>
						このまま書く（接続が戻ったら送ります）
					</button>
				</div>
			) }
		</div>
	);
}

function blankEntry( date: string, version: number ): Entry {
	return {
		date,
		exists: false,
		content: '',
		mood: null,
		tags: [],
		weather: null,
		location: null,
		char_count: 0,
		photos: [],
		version,
		updated_at: null,
	};
}

export function WeatherChip( { weather }: { weather: Weather } ) {
	const temps = weather.temp_max !== null && weather.temp_min !== null ? ` ${ Math.round( weather.temp_max ) }°/${ Math.round( weather.temp_min ) }°` : '';
	return (
		<span class="chip weather">
			{ weather.label }
			{ temps }
		</span>
	);
}

function DayHeader( { date, isToday }: Props ) {
	const prev = shiftDate( date, -1 );
	const next = shiftDate( date, 1 );
	const canNext = next <= today.value;
	const streak = stats.value.current_streak;

	return (
		<header class="day-head">
			<a class="day-nav" href={ dayHref( prev ) } data-nav aria-label="前の日" rel="prev">
				‹
			</a>
			<div class="day-title">
				<h1>
					{ yearLabel( date, today.value ) && <span class="day-year">{ yearLabel( date, today.value ) }</span> }
					{ dayLabel( date ) }
				</h1>
				{ isToday && streak > 0 && <span class="streak">{ streak }日連続</span> }
				{ ! isToday && (
					<a class="back-today" href={ dayHref( today.value ) } data-nav>
						今日へ
					</a>
				) }
			</div>
			{ canNext ? (
				<a class="day-nav" href={ dayHref( next ) } data-nav aria-label="次の日" rel="next">
					›
				</a>
			) : (
				<span class="day-nav is-disabled" aria-hidden="true">
					›
				</span>
			) }
		</header>
	);
}

interface EditorProps {
	entry: Entry;
	draft?: Draft;
	isToday: boolean;
}

function Editor( { entry, draft, isToday }: EditorProps ) {
	const textarea = useRef<HTMLTextAreaElement>( null );
	const initial = {
		content: draft?.fields.content ?? entry.content,
		mood: draft && 'mood' in draft.fields ? draft.fields.mood ?? null : entry.mood,
		tags: draft?.fields.tags ?? entry.tags,
		weather: draft?.fields.weather ?? entry.weather,
	};
	const [ mood, setMood ] = useState<Mood | null>( initial.mood );
	const [ chars, setChars ] = useState( countChars( initial.content ) );
	const [ tags, setTags ] = useState<string[]>( initial.tags );
	const [ weather, setWeather ] = useState<Weather | null>( initial.weather );
	const [ showTags, setShowTags ] = useState( initial.tags.length > 0 );
	const [ photos, setPhotos ] = useState<Photo[]>( entry.photos );

	const saver = useMemo(
		() =>
			new EntrySaver(
				entry.date,
				entry,
				( saved ) => {
				// The cache keeps what is on screen, so coming back to this day shows it.
				entryCache.set( saved.date, { ...saved, content: textarea.current?.value ?? saved.content } );
				if ( saved.date === today.value && saved.char_count > 0 && ! stats.value.written_today ) {
					api.getStats().then( ( s ) => ( stats.value = s ) ).catch( () => undefined );
				}
				},
				draft
			),
		[ entry.date ]
	);

	// Keep the background sync away from the day being edited here.
	useEffect( () => {
		openDates.add( entry.date );
		return () => {
			openDates.delete( entry.date );
			refreshDraftState();
		};
	}, [ entry.date ] );

	// Today's weather is added once, without asking.
	useEffect( () => {
		const s = settings.value;
		if ( ! isToday || weather || ! s.weather_enabled || s.weather_lat === null || s.weather_lng === null || ! navigator.onLine ) {
			return;
		}
		let alive = true;
		api.weather( s.weather_lat, s.weather_lng, entry.date )
			.then( ( w ) => {
				if ( alive ) {
					setWeather( w );
					saver.change( { weather: w } );
				}
			} )
			.catch( () => undefined );
		return () => {
			alive = false;
		};
	}, [ entry.date ] );

	// Set the text once; the textarea is uncontrolled so re-renders never
	// touch it while an IME composition is open.
	useEffect( () => {
		const el = textarea.current;
		if ( ! el ) {
			return;
		}
		el.value = initial.content;
		grow( el );
		if ( finePointer ) {
			el.focus( { preventScroll: true } );
			el.setSelectionRange( el.value.length, el.value.length );
		}
	}, [ entry.date ] );

	// Registered directly: Preact maps onCompositionEnd to a "CompositionEnd"
	// event, because browsers have no oncompositionend property to detect.
	useEffect( () => {
		const el = textarea.current;
		if ( ! el ) {
			return;
		}
		const onStart = () => saver.setComposing( true );
		const onEnd = () => {
			saver.setComposing( false );
			setChars( countChars( el.value ) );
			saver.change( { content: el.value } );
		};
		el.addEventListener( 'compositionstart', onStart );
		el.addEventListener( 'compositionend', onEnd );
		return () => {
			el.removeEventListener( 'compositionstart', onStart );
			el.removeEventListener( 'compositionend', onEnd );
		};
	}, [ saver ] );

	useEffect( () => {
		const onHide = () => {
			if ( document.visibilityState === 'hidden' ) {
				saver.flush( true );
			}
		};
		const onPageHide = () => saver.flush( true );
		const onOnline = () => saver.retryNow();
		document.addEventListener( 'visibilitychange', onHide );
		window.addEventListener( 'pagehide', onPageHide );
		window.addEventListener( 'online', onOnline );
		const unguard = beforeNavigate( () => saver.flush( true ) );
		return () => {
			document.removeEventListener( 'visibilitychange', onHide );
			window.removeEventListener( 'pagehide', onPageHide );
			window.removeEventListener( 'online', onOnline );
			unguard();
			saver.flush( true );
			saver.dispose();
		};
	}, [ saver ] );

	const onInput = ( event: Event ) => {
		const el = event.currentTarget as HTMLTextAreaElement;
		grow( el );
		if ( ( event as InputEvent ).isComposing ) {
			return;
		}
		setChars( countChars( el.value ) );
		saver.change( { content: el.value } );
	};

	const onKeyDown = ( event: KeyboardEvent ) => {
		const el = event.currentTarget as HTMLTextAreaElement;
		if ( event.isComposing ) {
			return;
		}
		if ( ( event.metaKey || event.ctrlKey ) && ! event.altKey && ( event.key === 'b' || event.key === 'i' ) ) {
			event.preventDefault();
			wrapSelection( el, event.key === 'b' ? '**' : '*' );
			afterProgrammaticEdit( el );
		}
		if ( ( event.metaKey || event.ctrlKey ) && event.key === 's' ) {
			event.preventDefault();
			saver.flush();
		}
	};

	const afterProgrammaticEdit = ( el: HTMLTextAreaElement ) => {
		grow( el );
		setChars( countChars( el.value ) );
		saver.change( { content: el.value } );
	};

	const insertTime = () => {
		const el = textarea.current;
		if ( ! el ) {
			return;
		}
		const start = el.selectionStart;
		const before = el.value.slice( 0, start );
		const prefix = before === '' || before.endsWith( '\n\n' ) ? '' : before.endsWith( '\n' ) ? '\n' : '\n\n';
		el.focus();
		el.setRangeText( `${ prefix }${ timeStamp( boot.timezone ) } `, start, el.selectionEnd, 'end' );
		afterProgrammaticEdit( el );
	};

	const changeTags = ( next: string[] ) => {
		setTags( next );
		saver.change( { tags: next } );
		saver.flush();
	};

	const changePhotos = ( next: Photo[] ) => {
		setPhotos( next );
		const cached = entryCache.get( entry.date );
		if ( cached ) {
			entryCache.set( entry.date, { ...cached, exists: true, photos: next } );
		}
	};

	const toggleMood = ( value: Mood ) => {
		const next = mood === value ? null : value;
		setMood( next );
		saver.change( { mood: next } );
		saver.flush();
	};

	const onResolve = ( choice: 'mine' | 'theirs' | 'both' ) => {
		const conflict = saver.conflict.value;
		const el = textarea.current;
		if ( ! conflict || ! el ) {
			return;
		}
		const mine = el.value;
		const theirs = conflict.server.content;
		if ( choice === 'theirs' ) {
			el.value = theirs;
			setMood( conflict.server.mood );
			setTags( conflict.server.tags );
			saver.resolve( null );
		} else {
			const content = choice === 'both' ? joinVersions( theirs, mine ) : mine;
			el.value = content;
			saver.resolve( { ...conflict.mine, content, mood, tags } );
		}
		afterResolve( el );
	};

	const afterResolve = ( el: HTMLTextAreaElement ) => {
		grow( el );
		setChars( countChars( el.value ) );
	};

	return (
		<>
			{ saver.conflict.value && <ConflictBanner onChoose={ onResolve } /> }

			{ weather && (
				<p class="meta">
					<WeatherChip weather={ weather } />
				</p>
			) }

			<div class="moods" role="group" aria-label="今日の気分">
				{ MOODS.map( ( m ) => (
					<button
						type="button"
						key={ m.value }
						class={ `mood mood-${ m.value }` }
						aria-pressed={ mood === m.value }
						onClick={ () => toggleMood( m.value ) }
					>
						{ m.label }
					</button>
				) ) }
			</div>

			<label class="visually-hidden" for="hibi-body">
				本文
			</label>
			<textarea
				id="hibi-body"
				ref={ textarea }
				class="body"
				placeholder={ isToday ? '今日はどんな日でしたか' : 'この日のことを書く' }
				autocomplete="off"
				spellcheck={ false }
				onInput={ onInput }
				onKeyDown={ onKeyDown }
			/>

			{ showTags && <TagEditor tags={ tags } onChange={ changeTags } /> }
			<PhotoStrip date={ entry.date } photos={ photos } editable onChange={ changePhotos } />
			{ isToday && <OnThisDayCard date={ entry.date } /> }

			<div class="toolbar" role="toolbar" aria-label="書く道具">
				<PhotoButton date={ entry.date } onAdded={ ( photo ) => changePhotos( [ ...photos, photo ] ) } />
				<button type="button" class="tool" onClick={ insertTime } onMouseDown={ ( e ) => e.preventDefault() }>
					時刻
				</button>
				<button type="button" class="tool" aria-pressed={ showTags } onClick={ () => setShowTags( ! showTags ) }>
					タグ
				</button>
				<span class="chars" aria-label={ `${ chars }文字` }>
					{ chars.toLocaleString( 'ja-JP' ) }字
				</span>
				<SaveStatus state={ saver.state.value } />
			</div>
		</>
	);
}

/** Fallback for browsers without CSS field-sizing. */
function grow( el: HTMLTextAreaElement ): void {
	if ( CSS.supports( 'field-sizing', 'content' ) ) {
		return;
	}
	el.style.height = 'auto';
	el.style.height = `${ el.scrollHeight }px`;
}

function countChars( text: string ): number {
	return text.replace( /\s+/gu, '' ).length;
}

function wrapSelection( el: HTMLTextAreaElement, mark: string ): void {
	const { selectionStart: start, selectionEnd: end, value } = el;
	const selected = value.slice( start, end );
	el.setRangeText( `${ mark }${ selected }${ mark }`, start, end, 'select' );
	if ( start === end ) {
		el.setSelectionRange( start + mark.length, start + mark.length );
	}
}

function joinVersions( theirs: string, mine: string ): string {
	if ( ! theirs.trim() ) {
		return mine;
	}
	if ( ! mine.trim() || theirs === mine ) {
		return theirs;
	}
	return `${ theirs.trimEnd() }\n\n---\n\n${ mine.trimStart() }`;
}

/** Previous/next day with Alt+←/→ and horizontal swipes. */
export function useDayShortcuts( date: string | null ): void {
	useEffect( () => {
		if ( ! date ) {
			return;
		}
		const go = ( offset: number ) => {
			const target = shiftDate( date, offset );
			if ( target <= today.value ) {
				navigate( dayHref( target ) );
			}
		};
		const onKey = ( event: KeyboardEvent ) => {
			if ( event.altKey && ! event.metaKey && ! event.ctrlKey && ( event.key === 'ArrowLeft' || event.key === 'ArrowRight' ) ) {
				event.preventDefault();
				go( event.key === 'ArrowLeft' ? -1 : 1 );
			}
		};

		let startX = 0;
		let startY = 0;
		let tracking = false;
		const onTouchStart = ( event: TouchEvent ) => {
			tracking = event.touches.length === 1;
			startX = event.touches[ 0 ].clientX;
			startY = event.touches[ 0 ].clientY;
		};
		const onTouchEnd = ( event: TouchEvent ) => {
			if ( ! tracking ) {
				return;
			}
			tracking = false;
			const t = event.changedTouches[ 0 ];
			const dx = t.clientX - startX;
			const dy = t.clientY - startY;
			const selection = document.getSelection();
			if ( Math.abs( dx ) > 70 && Math.abs( dy ) < 40 && ( ! selection || selection.isCollapsed ) ) {
				go( dx > 0 ? -1 : 1 );
			}
		};

		window.addEventListener( 'keydown', onKey );
		window.addEventListener( 'touchstart', onTouchStart, { passive: true } );
		window.addEventListener( 'touchend', onTouchEnd, { passive: true } );
		return () => {
			window.removeEventListener( 'keydown', onKey );
			window.removeEventListener( 'touchstart', onTouchStart );
			window.removeEventListener( 'touchend', onTouchEnd );
		};
	}, [ date ] );
}
