<?php

namespace TawasulOS;

trait MockTawasulOSTrait {

    /**
     * Mock global tawasul object.
     *
     * @param object $mockGibbon Object to use for mocking global tawasul object.
     * @return function Function to restore the original global tawasul object.
     */
    private function mockGlobalGibbon(object $mockGibbon)
    {
        global $tawasul;
        $tawasulToRestore = $tawasul ?? false;
        $tawasul = $mockGibbon; // replace global tawasul with the mock object.
        return function () use ($tawasulToRestore) {
            global $tawasul;
            // Unset global $tawasul if there is nothing to restore to.
            $tawasul = ($tawasulToRestore !== false) ? $tawasulToRestore : null;
        };
    }

}
