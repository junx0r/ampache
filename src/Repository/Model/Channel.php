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
 *
 */

declare(strict_types=0);

namespace Ampache\Repository\Model;

use Ampache\Config\AmpConfig;
use Ampache\Module\Channel\ChannelFactoryInterface;
use Ampache\Module\System\Dba;
use Ampache\Module\Tag\TagListUpdaterInterface;
use Ampache\Repository\ChannelRepositoryInterface;

final class Channel extends database_object implements ChannelInterface
{
    public const DEFAULT_PORT = 8200;

    private ChannelFactoryInterface $channelFactory;

    private TagListUpdaterInterface $tagListUpdater;

    private ChannelRepositoryInterface $channelRepository;

    public int $id;

    /** @var array<string, mixed>|null */
    private ?array $dbData = null;

    public function __construct(
        ChannelFactoryInterface $channelFactory,
        TagListUpdaterInterface $tagListUpdater,
        ChannelRepositoryInterface $channelRepository,
        int $id
    ) {
        $this->channelFactory    = $channelFactory;
        $this->tagListUpdater    = $tagListUpdater;
        $this->channelRepository = $channelRepository;
        $this->id                = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getFixedEndpoint(): int
    {
        return (int) ($this->getDbData()['fixed_endpoint'] ?? 0);
    }

    public function getUrl(): string
    {
        return $this->getDbData()['url'] ?? '';
    }

    public function getDescription(): string
    {
        return $this->getDbData()['description'] ?? '';
    }

    public function getName(): string
    {
        return $this->getDbData()['name'] ?? '';
    }

    public function getBitrate(): int
    {
        return (int) ($this->getDbData()['bitrate'] ?? 0);
    }

    public function getLoop(): int
    {
        return (int) ($this->getDbData()['loop'] ?? 0);
    }

    public function getRandom(): int
    {
        return (int) ($this->getDbData()['random'] ?? 0);
    }

    public function getStreamType(): string
    {
        return $this->getDbData()['stream_type'] ?? '';
    }

    public function getObjectId(): int
    {
        return (int) ($this->getDbData()['object_id'] ?? 0);
    }

    public function getObjectType(): string
    {
        return $this->getDbData()['object_type'] ?? '';
    }

    public function getPeakListeners(): int
    {
        return (int) ($this->getDbData()['peak_listeners'] ?? 0);
    }

    public function setPeakListeners(int $value): void
    {
        $this->dbData['peak_listeners'] = $value;
    }

    public function getMaxListeners(): int
    {
        return (int) ($this->getDbData()['max_listeners'] ?? 0);
    }

    public function getListeners(): int
    {
        return (int) ($this->getDbData()['listeners'] ?? 0);
    }

    public function setListeners(int $value): void
    {
        $this->dbData['listeners'] = $value;
    }

    public function getPid(): int
    {
        return (int) ($this->getDbData()['pid'] ?? 0);
    }

    public function setPid(int $value): void
    {
        $this->dbData['pid'] = $value;
    }

    public function getStartDate(): int
    {
        return (int) ($this->getDbData()['start_date'] ?? 0);
    }

    public function setStartDate(int $value): void
    {
        $this->dbData['start_date'] = $value;
    }

    public function getPort(): int
    {
        return (int) ($this->getDbData()['port'] ?? 0);
    }

    public function setPort(int $value): void
    {
        $this->dbData['port'] = $value;
    }

    public function getInterface(): string
    {
        return $this->getDbData()['interface'] ?? '';
    }

    public function setInterface(string $value): void
    {
        $this->dbData['interface'] = $value;
    }

    public function getIsPrivate(): int
    {
        return (int) ($this->getDbData()['is_private'] ?? 0);
    }

    public function isNew(): bool
    {
        return $this->getDbData() === [];
    }

    /**
     * @return array<string, mixed>
     */
    private function getDbData(): array
    {
        if ($this->dbData === null) {
            $this->dbData = $this->channelRepository->getDataById($this->id);
        }

        return $this->dbData;
    }

    /**
     * get_genre
     * @return string
     */
    public function get_genre()
    {
        $tags  = Tag::get_object_tags('channel', $this->id);
        $genre = "";
        if ($tags) {
            foreach ($tags as $tag) {
                $genre .= $tag['name'] . ' ';
            }
            $genre = trim((string)$genre);
        }

        return $genre;
    }

    public function delete(): bool
    {
        $this->channelRepository->delete($this);

        return true;
    }

    /**
     * create
     * @param string $name
     * @param string $description
     * @param string $url
     * @param string $object_type
     * @param integer $object_id
     * @param array $interface
     * @param array $port
     * @param string $admin_password
     * @param string $private
     * @param string $max_listeners
     * @param string $random
     * @param string $loop
     * @param string $stream_type
     * @param string $bitrate
     */
    public static function create(
        $name,
        $description,
        $url,
        $object_type,
        $object_id,
        $interface,
        $port,
        $admin_password,
        $private,
        $max_listeners,
        $random,
        $loop,
        $stream_type,
        $bitrate
    ): bool {
        if (!empty($name)) {
            $sql    = "INSERT INTO `channel` (`name`, `description`, `url`, `object_type`, `object_id`, `interface`, `port`, `fixed_endpoint`, `admin_password`, `is_private`, `max_listeners`, `random`, `loop`, `stream_type`, `bitrate`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = array(
                $name,
                $description,
                $url,
                $object_type,
                $object_id,
                $interface,
                $port,
                (!empty($interface) && !empty($port)),
                $admin_password,
                $private,
                $max_listeners,
                $random,
                $loop,
                $stream_type,
                $bitrate
            );

            return Dba::write($sql, $params);
        }

        return false;
    }

    /**
     * update
     * @param array $data
     * @return integer
     */
    public function update(array $data)
    {
        if (isset($data['edit_tags'])) {
            $this->tagListUpdater->update($data['edit_tags'], 'channel', $this->id, true);
        }

        $sql    = "UPDATE `channel` SET `name` = ?, `description` = ?, `url` = ?, `interface` = ?, `port` = ?, `fixed_endpoint` = ?, `admin_password` = ?, `is_private` = ?, `max_listeners` = ?, `random` = ?, `loop` = ?, `stream_type` = ?, `bitrate` = ?, `object_id` = ? " . "WHERE `id` = ?";
        $params = array(
            $data['name'],
            $data['description'],
            $data['url'],
            $data['interface'],
            $data['port'],
            (!empty($data['interface']) && !empty($data['port'])),
            $data['admin_password'],
            (int) $data['private'],
            $data['max_listeners'],
            (int) $data['random'],
            $data['loop'],
            $data['stream_type'],
            $data['bitrate'],
            $data['object_id'],
            $this->id
        );
        Dba::write($sql, $params);

        return $this->id;
    }

    /**
     * format
     * @param boolean $details
     */
    public function format($details = true)
    {
    }

    /**
     * @return array<int, array{
     *  user: int,
     *  id: int,
     *  name: string
     * }>
     */
    public function getTags(): array
    {
        return Tag::get_top_tags('channel', $this->id);
    }

    /**
     * get_keywords
     * @return array
     */
    public function get_keywords()
    {
        return array();
    }

    /**
     * get_fullname
     * @return string
     */
    public function get_fullname()
    {
        return $this->getName();
    }

    /**
     * @return array{object_type: string, object_id: int}|null
     */
    public function get_parent(): ?array
    {
        return null;
    }

    /**
     * get_childrens
     * @return array
     */
    public function get_childrens()
    {
        return array();
    }

    /**
     * search_childrens
     * @param string $name
     * @return array
     */
    public function search_childrens($name)
    {
        debug_event(self::class, 'search_childrens ' . $name, 5);

        return array();
    }

    /**
     * get_medias
     * @param string $filter_type
     * @return array
     */
    public function get_medias($filter_type = null)
    {
        $medias = array();
        if ($filter_type === null || $filter_type == 'channel') {
            $medias[] = array(
                'object_type' => 'channel',
                'object_id' => $this->id
            );
        }

        return $medias;
    }

    /**
     * get_user_owner
     * @return boolean|null
     */
    public function get_user_owner()
    {
        return null;
    }

    /**
     * get_default_art_kind
     * @return string
     */
    public function get_default_art_kind()
    {
        return 'default';
    }

    /**
     * get_description
     * @return string
     */
    public function get_description()
    {
        return $this->getDescription();
    }

    /**
     * display_art
     * @param integer $thumb
     * @param boolean $force
     */
    public function display_art($thumb = 2, $force = false)
    {
        if (Art::has_db($this->id, 'channel') || $force) {
            echo Art::display('channel', $this->id, $this->get_fullname(), $thumb);
        }
    }

    /**
     * get_target_object
     * @return ?Playlist
     */
    public function get_target_object()
    {
        $object = null;
        if ($this->getObjectType() == 'playlist') {
            $object = new Playlist($this->getObjectId());
            $object->format();
        }

        return $object;
    }

    /**
     * get_stream_url
     * show the internal interface used for the stream
     * e.g. http://0.0.0.0:8200/stream.mp3
     *
     * @return string
     */
    public function get_stream_url()
    {
        return "http://" . $this->getInterface() . ":" . $this->getPort() . "/stream." . $this->getStreamType();
    }

    /**
     * get_stream_proxy_url
     * show the external address used for the stream
     * e.g. https://music.com.au/channel/6/stream.mp3
     *
     * @return string
     */
    public function get_stream_proxy_url()
    {
        return AmpConfig::get('web_path') . '/channel/' . $this->getId() . '/stream.' . $this->getStreamType();
    }

    /**
     * get_stream_proxy_url_status
     * show the external address used for the stream
     * e.g. https://music.com.au/channel/6/status.xsl
     *
     * @return string
     */
    public function get_stream_proxy_url_status()
    {
        return AmpConfig::get('web_path') . '/channel/' . $this->id . '/status.xsl';
    }

    /**
     * get_channel_state
     * @return string
     */
    public function get_channel_state()
    {
        if ($this->channelFactory->createChannelOperator($this)->checkChannel()) {
            $state = T_("Running");
        } else {
            $state = T_("Stopped");
        }

        return $state;
    }

    /**
     * get_catalogs
     *
     * Get all catalog ids related to this item.
     * @return integer[]
     */
    public function get_catalogs()
    {
        return array();
    }

    /**
     * play_url
     * @param string $additional_params
     * @param string $player
     * @param boolean $local
     * @return string
     */
    public function play_url($additional_params = '', $player = null, $local = false)
    {
        return $this->get_stream_proxy_url() . '?rt=' . time() . '&filename=' . urlencode($this->getName()) . '.' . $this->getStreamType() . $additional_params;
    }

    /**
     * get_stream_types
     * @param string $player
     * @return string[]
     */
    public function get_stream_types($player = null)
    {
        // Transcode is mandatory to keep a consistant stream
        return array('transcode');
    }

    /**
     * get_stream_name
     * @return string
     */
    public function get_stream_name()
    {
        return $this->get_fullname();
    }

    /**
     * @param integer $user
     * @param string $agent
     * @param array $location
     * @param integer $date
     * @return boolean
     */
    public function set_played($user, $agent, $location, $date = null)
    {
        // Do nothing
        unset($user, $agent, $location, $date);

        return false;
    }

    /**
     * @param integer $user
     * @param string $agent
     * @param integer $date
     * @return boolean
     */
    public function check_play_history($user, $agent, $date)
    {
        // Do nothing
        unset($user, $agent, $date);

        return false;
    }

    /**
     * @param $target
     * @param $player
     * @param array $options
     * @return boolean
     */
    public function get_transcode_settings($target = null, $player = null, $options = array())
    {
        return false;
    }

    public function remove()
    {
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
