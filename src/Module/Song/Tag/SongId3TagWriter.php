<?php
/*
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
 * Copyright 2001 - 2020 Ampache.org
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Ampache\Module\Song\Tag;

use Ampache\Config\ConfigContainerInterface;
use Ampache\Config\ConfigurationKeyEnum;
use Ampache\Module\Util\UtilityFactoryInterface;
use Ampache\Repository\Model\Art;
use Ampache\Repository\Model\Catalog;
use Ampache\Repository\Model\Song;
use Ampache\Module\System\LegacyLogger;
use Ampache\Module\Util\VaInfo;
use Psr\Log\LoggerInterface;

final class SongId3TagWriter implements SongId3TagWriterInterface
{
    private ConfigContainerInterface $configContainer;

    private LoggerInterface $logger;

    private UtilityFactoryInterface $utilityFactory;

    public function __construct(
        ConfigContainerInterface $configContainer,
        LoggerInterface $logger,
        UtilityFactoryInterface $utilityFactory
    ) {
        $this->configContainer = $configContainer;
        $this->logger          = $logger;
        $this->utilityFactory  = $utilityFactory;
    }

    /**
     * Write the current song id3 metadata to the file
     */
    public function write(
        Song $song,
        ?array $data = null,
        ?array $changed = null
    ): void {
        if ($this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::WRITE_ID3) === false) {
            return;
        }

        $catalog = Catalog::create_from_id($song->catalog);
        if ($catalog->get_type() == 'local') {
            $this->logger->debug(
                sprintf('Writing id3 metadata to file %s', $song->file),
                [LegacyLogger::CONTEXT_TYPE => __CLASS__]
            );

            if ($this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::ENABLE_CUSTOM_METADATA) === true) {
                foreach ($song->getMetadata() as $metadata) {
                    $meta[$metadata->getField()->getName()] = $metadata->getData();
                }
            }

            $id3    = $this->utilityFactory->createVaInfo($song->file);
            $result = $id3->read_id3();
            if ($result['fileformat'] == 'mp3') {
                $tdata = $result['tags']['id3v2'];
                $meta  = $this->getMetadata($song);
            } else {
                $tdata = $result['tags']['vorbiscomment'];
                $meta  = $this->getVorbisMetadata($song);
            }
            $ndata = $id3->prepare_id3_frames($tdata);

            if (isset($changed)) {
                foreach ($changed as $key => $value) {
                    switch ($value) {
                        case 'artist':
                        case 'artist_name':
                            $ndata['artist'][0] = $song->f_artist;
                            break;
                        case 'album':
                        case 'album_name':
                            $ndata['album'][0] = $song->f_album;
                            break;
                        case 'track':
                            $ndata['track_number'][0] = $data['track'];
                            break;
                        case 'label':
                            $ndata['publisher'][0] = $data['label'];
                            break;
                        case 'edit_tags':
                            $ndata['genre'][0] = $data['edit_tags'];
                            break;
                        default:
                            $ndata[$value][0] = $data[$value];
                            break;
                    }
                }
                $pics = array();
                if (isset($data['id3v2']['APIC'])) {
                    $pics = Art::prepare_pics($data['id3v2']['APIC']);
                }
                $ndata = array_merge($pics, $ndata);
            } else {
                // Fill in existing tag frames
                foreach ($meta as $key => $value) {
                    if ($key != 'text' && $key != 'totaltracks') {
                        $ndata[$key][0] = $meta[$key] ?:'';
                    }
                }

                $art = new Art($song->album, 'album');
                if ($art->has_db_info()) {
                    $album_image                                   = $art->get(true);
                    $ndata['attached_picture'][0]['description']   = $song->f_album;
                    $ndata['attached_picture'][0]['data']          = $album_image;
                    $ndata['attached_picture'][0]['picturetypeid'] = '3';
                    $ndata['attached_picture'][0]['mime']          = $art->raw_mime;
                }
                $art = new Art($song->artist, 'artist');
                if ($art->has_db_info()) {
                    $artist_image                                   = $art->get(true);
                    $i                                              = (!empty($album_image)) ? 1 : 0;
                    $ndata['attached_picture'][$i]['description']   = $song->f_artist;
                    $ndata['attached_picture'][$i]['data']          = $artist_image;
                    $ndata['attached_picture'][$i]['picturetypeid'] = '8';
                    $ndata['attached_picture'][$i]['mime']          = $art->raw_mime;
                }
            }
            $id3->write_id3($ndata);
        }
    }

    private function getVorbisMetadata(
        Song $song
    ): array {
        $meta = [];

        $meta['date']        = $song->year;
        $meta['time']        = $song->time;
        $meta['title']       = $song->title;
        $meta['comment']     = $song->comment;
        $meta['album']       = $song->f_album_full;
        $meta['artist']      = $song->f_artist_full;
        $meta['albumartist'] = $song->f_albumartist_full;
        $meta['composer']    = $song->composer;
        $meta['publisher']   = $song->f_publisher;
        $meta['track']       = $song->f_track;
        $meta['discnumber']  = $song->disk;
        $meta['genre']       = [];

        if (!empty($song->tags)) {
            foreach ($song->tags as $tag) {
                if (!in_array($tag['name'], $meta['genre'])) {
                    $meta['genre'][] = $tag['name'];
                }
            }
        }
        $meta['genre'] = implode(',', $meta['genre']);

        return $meta;
    }

    /**
     * Get an array of metadata for writing id3 file tags.
     */
    private function getMetadata(
        Song $song
    ): array {
        $meta = [];

        $meta['year']          = $song->year;
        $meta['time']          = $song->time;
        $meta['title']         = $song->title;
        $meta['comment']       = $song->comment;
        $meta['album']         = $song->f_album_full;
        $meta['artist']        = $song->f_artist_full;
        $meta['band']          = $song->f_albumartist_full;
        $meta['composer']      = $song->composer;
        $meta['publisher']     = $song->f_publisher;
        $meta['track_number']  = $song->f_track;
        $meta['part_of_a_set'] = $song->disk;
        $meta['genre']         = [];

        if (!empty($song->tags)) {
            foreach ($song->tags as $tag) {
                if (!in_array($tag['name'], $meta['genre'])) {
                    $meta['genre'][] = $tag['name'];
                }
            }
        }
        $meta['genre'] = implode(',', $meta['genre']);

        return $meta;
    }
}
