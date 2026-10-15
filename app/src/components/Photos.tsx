import { useRef, useState } from 'preact/hooks';
import { api, ApiError } from '../api';
import { prepareForUpload } from '../photo-resize';
import type { Photo } from '../types';
import { t } from '../i18n';

interface Props {
	date: string;
	photos: Photo[];
	editable: boolean;
	onChange: ( photos: Photo[] ) => void;
}

/** Thumbnails under the entry, with a viewer and (when editing) delete. */
export function PhotoStrip( { date, photos, editable, onChange }: Props ) {
	const [ open, setOpen ] = useState<Photo | null>( null );
	const dialog = useRef<HTMLDialogElement>( null );

	const show = ( photo: Photo ) => {
		setOpen( photo );
		requestAnimationFrame( () => dialog.current?.showModal() );
	};
	const close = () => {
		dialog.current?.close();
		setOpen( null );
	};
	const remove = async ( photo: Photo ) => {
		if ( ! window.confirm( t( 'photo.confirm_delete' ) ) ) {
			return;
		}
		try {
			await api.deletePhoto( photo.id );
			onChange( photos.filter( ( p ) => p.id !== photo.id ) );
			close();
		} catch {
			window.alert( t( 'photo.delete_failed' ) );
		}
	};

	if ( ! photos.length ) {
		return null;
	}

	return (
		<>
			<ul class="photos" aria-label={ t( 'photo.list', photos.length ) }>
				{ photos.map( ( photo ) => (
					<li key={ photo.id }>
						<button type="button" class="photo-thumb" onClick={ () => show( photo ) } aria-label={ t( 'photo.enlarge' ) }>
							<img src={ photo.thumb } alt="" loading="lazy" width={ 88 } height={ 88 } />
						</button>
					</li>
				) ) }
			</ul>
			<dialog ref={ dialog } class="photo-viewer" onClose={ () => setOpen( null ) } aria-label={ t( 'photo.viewer' ) } data-date={ date }>
				{ open && (
					<>
						<img src={ open.medium } alt="" />
						<div class="photo-actions">
							<a href={ open.full } target="_blank" rel="noopener">
								{ t( 'photo.original' ) }
							</a>
							{ editable && (
								<button type="button" class="danger" onClick={ () => remove( open ) }>
									{ t( 'photo.delete' ) }
								</button>
							) }
							<button type="button" onClick={ close } autofocus>
								{ t( 'common.close' ) }
							</button>
						</div>
					</>
				) }
			</dialog>
		</>
	);
}

/** Toolbar button that uploads photos one after another. */
export function PhotoButton( { date, onAdded }: { date: string; onAdded: ( photo: Photo ) => void } ) {
	const input = useRef<HTMLInputElement>( null );
	const [ busy, setBusy ] = useState( 0 );

	const onPick = async () => {
		const files = Array.from( input.current?.files ?? [] );
		if ( input.current ) {
			input.current.value = '';
		}
		for ( const file of files ) {
			setBusy( ( n ) => n + 1 );
			try {
				const { blob, name } = await prepareForUpload( file );
				onAdded( await api.uploadPhoto( date, blob, name ) );
			} catch ( err ) {
				window.alert( err instanceof ApiError ? err.message : t( 'photo.add_failed' ) );
			} finally {
				setBusy( ( n ) => n - 1 );
			}
		}
	};

	return (
		<>
			<button type="button" class="tool" onClick={ () => input.current?.click() } disabled={ busy > 0 } aria-busy={ busy > 0 }>
				{ busy > 0 ? t( 'common.sending' ) : t( 'photo.button' ) }
			</button>
			<input ref={ input } type="file" accept="image/*,.heic,.heif" multiple hidden onChange={ onPick } />
		</>
	);
}
