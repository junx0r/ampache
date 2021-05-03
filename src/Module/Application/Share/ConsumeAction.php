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

namespace Ampache\Module\Application\Share;

use Ampache\Config\ConfigContainerInterface;
use Ampache\Config\ConfigurationKeyEnum;
use Ampache\Module\Application\Batch\DefaultAction;
use Ampache\Module\Application\Stream\DownloadAction;
use Ampache\Module\Playback\PlaybackFactoryInterface;
use Ampache\Module\Playback\Stream_Playlist;
use Ampache\Module\Share\ShareValidatorInterface;
use Ampache\Module\Util\UiInterface;
use Ampache\Repository\Model\ModelFactoryInterface;
use Ampache\Repository\Model\Preference;
use Ampache\Module\Application\ApplicationActionInterface;
use Ampache\Module\Application\Exception\AccessDeniedException;
use Ampache\Module\Authorization\AccessLevelEnum;
use Ampache\Module\Authorization\Check\NetworkCheckerInterface;
use Ampache\Module\Authorization\GuiGatekeeperInterface;
use Ampache\Module\System\Core;
use Ampache\Repository\Model\ShareInterface;
use Ampache\Repository\ShareRepositoryInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ConsumeAction implements ApplicationActionInterface
{
    public const REQUEST_KEY = 'consume';

    private ConfigContainerInterface $configContainer;

    private NetworkCheckerInterface $networkChecker;

    private ShareRepositoryInterface $shareRepository;

    private ContainerInterface $dic;

    private ModelFactoryInterface $modelFactory;

    private ShareValidatorInterface $shareValidator;

    private PlaybackFactoryInterface $playbackFactory;

    private UiInterface $ui;

    public function __construct(
        ConfigContainerInterface $configContainer,
        NetworkCheckerInterface $networkChecker,
        ShareRepositoryInterface $shareRepository,
        ContainerInterface $dic,
        ModelFactoryInterface $modelFactory,
        ShareValidatorInterface $shareValidator,
        PlaybackFactoryInterface $playbackFactory,
        UiInterface $ui
    ) {
        $this->configContainer = $configContainer;
        $this->networkChecker  = $networkChecker;
        $this->shareRepository = $shareRepository;
        $this->dic             = $dic;
        $this->modelFactory    = $modelFactory;
        $this->shareValidator  = $shareValidator;
        $this->playbackFactory = $playbackFactory;
        $this->ui              = $ui;
    }

    public function run(ServerRequestInterface $request, GuiGatekeeperInterface $gatekeeper): ?ResponseInterface
    {
        Preference::init();

        if (!$this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::SHARE)) {
            throw new AccessDeniedException('Access Denied: sharing features are not enabled.');
        }

        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

        /**
         * If Access Control is turned on then we don't
         * even want them to be able to get to the login
         * page if they aren't in the ACL
         */
        if ($this->configContainer->isFeatureEnabled(ConfigurationKeyEnum::ACCESS_CONTROL)) {
            if (!$this->networkChecker->check(AccessLevelEnum::TYPE_INTERFACE, null, AccessLevelEnum::LEVEL_GUEST)) {
                throw new AccessDeniedException(
                    sprintf(
                        'Access Denied:%s is not in the Interface Access list',
                        Core::get_server('REMOTE_ADDR')
                    )
                );
            }
        } // access_control is enabled

        $share_id = Core::get_request('id');
        $secret   = $_REQUEST['secret'];

        $share = $this->modelFactory->createShare((int) $share_id);
        if (empty($action) && $share->getId()) {
            if ($share->getAllowStream()) {
                $action = 'stream';
            } elseif ($share->getAllowDownload()) {
                $action = 'download';
            }
        }

        if (!$this->shareValidator->isValid($share, $secret, $action)) {
            throw new AccessDeniedException();
        }

        $this->shareRepository->saveAccess(
            $share,
            time()
        );

        if ($action == 'download') {
            if ($share->getObjectType() == 'song' || $share->getObjectType() == 'video') {
                $_REQUEST['action']                        = 'download';
                $_REQUEST['type']                          = $share->getObjectType();
                $_REQUEST[$share->getObjectType() . '_id'] = $share->getObjectId();

                return $this->dic->get(DownloadAction::class)->run($request, $gatekeeper);
            } else {
                $_REQUEST['action'] = $share->getObjectType();
                $_REQUEST['id']     = $share->getObjectId();

                return $this->dic->get(DefaultAction::class)->run($request, $gatekeeper);
            }
        } elseif ($action == 'stream') {
            $this->ui->show(
                'show_share.inc.php',
                [
                    'share' => $share,
                    'playlist' => $this->create_fake_playlist($share)
                ]
            );
        } else {
            throw new AccessDeniedException('Access Denied: unknown action.');
        }

        return null;
    }

    private function create_fake_playlist(ShareInterface $share): Stream_Playlist
    {
        $playlist = $this->playbackFactory->createStreamPlaylist('-1');
        $medias   = [];

        switch ($share->getObjectType()) {
            case 'album':
            case 'playlist':
                $songs  = $share->getObject()->get_medias('song');
                foreach ($songs as $song) {
                    $medias[] = $song;
                }
                break;
            default:
                $medias[] = [
                    'object_type' => $share->getObjectType(),
                    'object_id' => $share->getObjectId(),
                ];
                break;
        }

        $playlist->add($medias,
            sprintf(
                '&share_id=%d&share_secret=%s',
                $share->getId(),
                $share->getSecret()
            )
        );

        return $playlist;
    }
}
