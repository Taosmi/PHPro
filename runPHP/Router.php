<?php

namespace runPHP;

/**
 * Analyze the request to know which application, API or view is involved to
 * load and run them.
 *
 * @author Miguel Angel Garcia
 *
 * Copyright 2014 TAOSMI Technology
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
class Router {

    /**
     * Get a backward controller iterating throw the dynamic part of the path.
     * If no controller is available, return null.
     *
     * @param  array   $request  A request information.
     * @param  string  $root     Static part of the path.
     * @param  string  $path     Dynamic part of the path.
     * @return string            A controller path or null.
     */
    private function getBackwardPath (&$request, $root, $path) {
        if ($path) {
            $pathParts = explode('/', substr($path, 1));
            // Loop throw the dynamic path.
            $newPath = '';
            while ($pathParts && (file_exists($root.$newPath.'/'.$pathParts[0].'.php') || is_dir($root.$newPath.'/'.$pathParts[0]))) {
                $newPath .= '/'.array_shift($pathParts);
            }
            // If a part of the path is valid, set the rest as parameters.
            if ($newPath && !is_dir($root.$newPath)) {
                $request['params'] = $pathParts;
                return $root.$newPath;
            }
        }
        return null;
    }


    /**
     * Get the API name involved with the request. If no API is available,
     * return null.
     *
     * @param  array  $request  A request information.
     * @return string           An API name or null.
     */
    public static function getApi (&$request) {
        // Build the API full class name.
        $api = APIS.$request['path'].'/'.$request['name'];
        // Check the API and get the class namespace.
        if (file_exists($api.'.php')) {
            return str_replace('/', '\\', substr($api, strlen(APP) + 1));
        }
        // Get a backwards API controller.
        $api = self::getBackwardPath($request, APIS, $request['path'].'/'.$request['name']);
        return str_replace('/', '\\', substr($api, strlen(APP) + 1));
    }

    /**
     * Analyze the HTTP request and retrieve the relevant request information.
     *
     * @return array  The request information.
     */
    public static function getRequest () {
        // Get the relevant request data.
        $url = pathinfo(str_replace('?'.$_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']));
        return array(
            'app' => $_SERVER['SERVER_NAME'],
            'cfg' => parse_ini_file(WEBAPPS.DIRECTORY_SEPARATOR.$_SERVER['SERVER_NAME'].DIRECTORY_SEPARATOR.'app.cfg', true),
            'from' => $_SERVER['REMOTE_ADDR'],
            'method' => $_SERVER['REQUEST_METHOD'],
            'mime' => strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'])[0])),
            'url' => $_SERVER['REQUEST_URI'],
            'path' => $url['dirname'] === '/' ? '' : $url['dirname'],
            'name' => $url['filename'] ? $url['filename'] : 'index',
            'format' => $url['extension'],
            'params' => array()
        );
    }

    /**
     * Get the view name involved with the request. If no view is available,
     * return null.
     *
     * @param  array  $request  A request information.
     * @return string           A view name or null.
     */
    public static function getView (&$request) {
        // Build the full file name.
        $file = VIEWS.$request['path'].'/'.$request['name'];
        // Check the view and get the file name.
        if (file_exists($file.'.php')) {
            return $file;
        }
        // Get a backwards view file name.
        return self::getBackwardPath($request, VIEWS, $request['path'].'/'.$request['name']);
    }

}