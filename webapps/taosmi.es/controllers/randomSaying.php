<?php

namespace controllers;
use ProWeb\Controller, ProWeb\Plugins\JsonView;
use domain\SayingRepository;

/**
 * The controller for /randomSaying.
 */
class randomSaying extends Controller {

    /**
     * Main function.
     */
    public function main () {
        // Gets a random saying.
        $sayingRepo = new SayingRepository();
        $saying = $sayingRepo->findRandom();
        // Render the page.
        $template = new JsonView($saying);
        $template->render();
    }
}