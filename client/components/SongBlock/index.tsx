import { Song } from '~logic/Song';
import { Link } from 'react-router-dom';
import React from 'react';

import style from './index.styl';
import { useStore } from '~store';

interface SongBlockProps {
    song: Song;
    playSong: (song: Song) => void;
    className?: string;
}

const SongBlock = (props: SongBlockProps) => {
    const { currentPlayingSong } = useStore();

    const currentlyPlaying = props.song.id === currentPlayingSong?.id;

    return (
        <div
            onClick={() => {
                props.playSong(props.song);
            }}
            className={`card ${props.className} ${style.songBlock} ${
                currentlyPlaying ? 'nowPlaying' : ''
            }`}
            tabIndex={1}
        >
            <img src={props.song.art} alt='Album Cover' />
            <div className={style.details}>
                <div className={`card-title ${style.title}`}>
                    {props.song.title}
                </div>
                <div className={style.bottom}>
                    <Link
                        to={`/album/${props.song.album.id}`}
                        onClick={(e) => {
                            e.stopPropagation();
                        }}
                    >
                        {props.song.album.name}
                    </Link>
                    <Link
                        to={`/artist/${props.song.artist.id}`}
                        onClick={(e) => {
                            e.stopPropagation();
                        }}
                    >
                        {props.song.artist.name}
                    </Link>
                </div>
            </div>
        </div>
    );
};

export default SongBlock;
