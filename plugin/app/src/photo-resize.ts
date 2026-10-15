// Shrinks photos to 2560 px on the long edge and re-encodes them as JPEG
// before upload. The canvas copy carries no EXIF, so GPS never leaves the
// device; the server strips it again in any case.

const MAX_EDGE = 2560;
const QUALITY = 0.9;

export async function prepareForUpload( file: File ): Promise<{ blob: Blob; name: string }> {
	let bitmap: ImageBitmap;
	try {
		bitmap = await createImageBitmap( file, { imageOrientation: 'from-image' } );
	} catch {
		// e.g. HEIC outside Safari: the server converts it.
		return { blob: file, name: file.name };
	}

	const scale = Math.min( 1, MAX_EDGE / Math.max( bitmap.width, bitmap.height ) );
	const width = Math.round( bitmap.width * scale );
	const height = Math.round( bitmap.height * scale );

	const canvas = document.createElement( 'canvas' );
	canvas.width = width;
	canvas.height = height;
	const ctx = canvas.getContext( '2d' );
	if ( ! ctx ) {
		bitmap.close();
		return { blob: file, name: file.name };
	}
	ctx.fillStyle = '#fff';
	ctx.fillRect( 0, 0, width, height );
	ctx.drawImage( bitmap, 0, 0, width, height );
	bitmap.close();

	const blob = await new Promise<Blob | null>( ( resolve ) => canvas.toBlob( resolve, 'image/jpeg', QUALITY ) );
	if ( ! blob ) {
		return { blob: file, name: file.name };
	}
	return { blob, name: file.name.replace( /\.[^.]+$/, '' ) + '.jpg' };
}
