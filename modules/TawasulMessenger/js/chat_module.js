/*
TawasulOS: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the TawasulOS community (https://tawasuledu.org/about/)
Copyright © 2010, TawasulOS Foundation
TawasulOS™, TawasulOS Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

/*
The platform adds modules/TawasulChat/js/module.js to every page in this module
unconditionally, and serves a 404 page for the request when the file is absent.
The browser then tries to parse that HTML as JavaScript and throws
"Unexpected token '<'" — harmless to the chat screen, whose client is
js/chat.js and which is registered separately, but it is a console error on
every page load and an avoidable broken request.

The convention in this codebase is that module.js holds the licence header and
whatever module-wide script the module needs; Messenger and several others ship
the header alone. The chat screen is self-contained in chat.js, so there is
nothing to put here yet.
*/
