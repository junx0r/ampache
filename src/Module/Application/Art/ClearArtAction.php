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

namespace Ampache\Module\Application\Art;

use Ampache\Repository\Model\ModelFactoryInterface;
use Ampache\Module\Application\Exception\AccessDeniedException;
use Ampache\Module\Authorization\GuiGatekeeperInterface;
use Ampache\Module\System\Core;
use Ampache\Module\Util\UiInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ClearArtAction extends AbstractArtAction
{
    public const REQUEST_KEY = 'clear_art';

    private ModelFactoryInterface $modelFactory;

    private UiInterface $ui;

    public function __construct(
        ModelFactoryInterface $modelFactory,
        UiInterface $ui
    ) {
        parent::__construct($modelFactory);

        $this->modelFactory = $modelFactory;
        $this->ui           = $ui;
    }

    public function run(ServerRequestInterface $request, GuiGatekeeperInterface $gatekeeper): ?ResponseInterface
    {
        $queryParams = $request->getQueryParams();

        $objectType = $queryParams['object_type'] ?? '';
        $objectId   = (int) ($queryParams['object_id'] ?? 0);

        $burl = '';
        if (array_key_exists('burl', $queryParams)) {
            $burl = base64_decode($queryParams['burl']);
        }

        $item = $this->getItem($gatekeeper, $objectType, $objectId);

        $art = $this->modelFactory->createArt($item->getId(), $objectType);
        $art->reset();

        $this->ui->showHeader();
        $this->ui->showConfirmation(
            T_('No Problem'),
            T_('Art information has been removed from the database'),
            $burl
        );
        $this->ui->showQueryStats();
        $this->ui->showFooter();

        return null;
    }
}
