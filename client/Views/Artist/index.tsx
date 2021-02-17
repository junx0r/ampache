import React, { useContext, useEffect, useState } from 'react';
import { Artist, flagArtist, getArtist, updateArtistInfo } from '~logic/Artist';
import { User } from '~logic/User';
import AmpacheError from '~logic/AmpacheError';
import AlbumDisplay from '~components/AlbumDisplay/';
import { playSongFromAlbum } from '~Helpers/playAlbumHelper';
import { MusicContext } from '~Contexts/MusicContext';
import ReactLoading from 'react-loading';
import { toast } from 'react-toastify';
import { generateSongsFromArtist } from '~logic/Playlist_Generate';
import { updateArtistArt } from '~logic/Art';
import Button, { ButtonColors, ButtonSize } from '~components/Button';
import SimpleRating from '~components/SimpleRating';

import style from './index.styl';
import { flagAlbum } from '~logic/Album';

interface ArtistViewProps {
    user: User;
    match: {
        params: {
            artistID: string;
        };
    };
}

const ArtistView: React.FC<ArtistViewProps> = (props: ArtistViewProps) => {
    const musicContext = useContext(MusicContext);

    const [artist, setArtist] = useState<Artist>(null);
    const [error, setError] = useState<Error | AmpacheError>(null);

    useEffect(() => {
        if (props.match.params.artistID != null) {
            getArtist(props.match.params.artistID, props.user.authKey, true)
                .then((data) => {
                    setArtist(data);
                })
                .catch((error) => {
                    toast.error(
                        '😞 Something went wrong getting information about the artist.'
                    );
                    setError(error);
                });
        }
    }, [props.match.params.artistID, props.user.authKey]);

    const playRandomArtistSongs = () => {
        generateSongsFromArtist(artist.id, props.user.authKey)
            .then((songs) => {
                console.log(songs);
                songs.sort(() => Math.random() - 0.5);

                musicContext.startPlayingWithNewQueue(songs);
                //TODO: When working
            })
            .catch((error) => {
                toast.error(
                    '😞 Something went wrong generating songs from artist.'
                );
                setError(error);
            });
    };

    /*TODO: This is sort of a temp method to allow for easy updates, but in future the client should maybe check for missing data and handle it automatically*/
    const handleArtistUpdate = () => {
        updateArtistArt(artist.id, true, props.user.authKey)
            .then(() => {
                toast.success('Art Updated Successfully');
            })
            .catch((error) => {
                toast.error(
                    `😞 Something went wrong updating artist art. ${error}`
                );
            });
        updateArtistInfo(artist.id, props.user.authKey)
            .then(() => {
                toast.success('Info Updated Successfully');
            })
            .catch((error) => {
                toast.error(
                    `😞 Something went wrong updating artist info. ${error}`
                );
            });
    };

    const handleFlagArtist = (artistID: string, favorite: boolean) => {
        flagArtist(artistID, favorite, props.user.authKey)
            .then(() => {
                const newArtist = { ...artist };
                newArtist.flag = favorite;
                setArtist(newArtist);
                if (favorite) {
                    return toast.success('Artist added to favorites');
                }
                toast.success('Artist removed from favorites');
            })
            .catch((err) => {
                if (favorite) {
                    toast.error(
                        '😞 Something went wrong adding the artist to favorites.'
                    );
                } else {
                    toast.error(
                        '😞 Something went wrong removing the artist from favorites.'
                    );
                }
                setError(err);
            });
    };

    const handleFlagAlbum = (albumID: string, favorite: boolean) => {
        flagAlbum(albumID, favorite, props.user.authKey)
            .then(() => {
                const newArtist = { ...artist };
                newArtist.albums = newArtist.albums.map((album) => {
                    if (album.id === albumID) {
                        album.flag = favorite;
                    }
                    return album;
                });
                setArtist(newArtist);
                if (favorite) {
                    return toast.success('Album added to favorites');
                }
                toast.success('Album removed from favorites');
            })
            .catch(() => {
                if (favorite) {
                    toast.error(
                        '😞 Something went wrong adding album to favorites.'
                    );
                } else {
                    toast.error(
                        '😞 Something went wrong removing album from favorites.'
                    );
                }
            });
    };

    if (error) {
        return (
            <div className={style.artistPage}>
                <span>Error: {error.message}</span>
            </div>
        );
    }
    return (
        <div className={style.artistPage}>
            {!artist && <ReactLoading color='#FF9D00' type={'bubbles'} />}
            {artist && (
                <div className={style.artistInfo}>
                    <div className={style.imageContainer}>
                        <img
                            src={artist.art}
                            alt={`Photo of ${artist.name}`}
                            onClick={handleArtistUpdate}
                        />
                    </div>
                    <div className={style.details}>
                        <div className={style.rating}>
                            <SimpleRating
                                value={artist.rating}
                                fav={artist.flag}
                                itemID={artist.id}
                                setFlag={handleFlagArtist}
                            />
                        </div>
                        <div className={`card-title ${style.name}`}>
                            {artist.name}
                        </div>
                        <div className={style.summary}>{artist.summary}</div>
                        <div className={style.actions}>
                            <Button
                                onClick={playRandomArtistSongs}
                                size={ButtonSize.medium}
                                color={ButtonColors.green}
                                text='Shuffle'
                            />
                        </div>
                        <div className={style.summary}>{artist.summary}</div>
                    </div>
                </div>
            )}
            <div className={`album-grid ${style.albums}`}>
                {!artist && <ReactLoading color='#FF9D00' type={'bubbles'} />}
                {artist &&
                    artist.albums.map((theAlbum) => {
                        return (
                            <AlbumDisplay
                                album={theAlbum}
                                playSongFromAlbum={(albumID, random) => {
                                    playSongFromAlbum(
                                        theAlbum.id,
                                        random,
                                        props.user.authKey,
                                        musicContext
                                    );
                                }}
                                flagAlbum={handleFlagAlbum}
                                key={theAlbum.id}
                                className={style.albumDisplayContainer}
                            />
                        );
                    })}
            </div>
        </div>
    );
};

export default ArtistView;
