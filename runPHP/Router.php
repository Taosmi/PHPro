<?php

namespace runPHP;

/**
 * Analyze the request to know which application and which controller are
 * involved to load and run them.
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
     * Get the controller name involved with the request. If no controller is
     * available, returns null.
     *
     * @param  array   $request  A request information.
     * @return string            The controller name or null.
     */
    public static function getController (&$request) {
        // Build the controller full class name.
        $controller = $request['cfg']['PATHS']['controllers'].$request['controller']['path'].'/'.$request['controller']['name'];
        // Check the controller and get the class namespace.
        if (file_exists(APP.$controller.'.php')) {
            return str_replace('/', '\\', substr($controller, 1));
        }
        // Check if there is a backwards controller.
        if ($request['controller']['path']) {
            $path = '';
            $root = APP.$request['cfg']['PATHS']['controllers'];
            $pathParts = explode('/', substr($request['controller']['path'], 1));
            // Loop throw the URL path.
            while ($pathParts) {
                if (!file_exists($root.$path.'/'.$pathParts[0].'.php')) {
                    break;
                }
                $path .= '/'.array_shift($pathParts);
            }
            // If a part of the path is valid, set the rest as parameters.
            if ($path) {
                $request['controller']['params'] = $pathParts;
                $request['controller']['params'][] = $request['controller']['name'];
                // Return the backwards controller.
                return str_replace('/', '\\', $request['cfg']['PATHS']['controllers'].$path);
            }
        }
        return null;
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
            'url' => $_SERVER['REQUEST_URI'],
            'controller' => array(
                'path' => $url['dirname'] === '/' ? '' : $url['dirname'],
                'name' => $url['filename'] ? $url['filename'] : 'index',
                'format' => $url['extension'],
                'params' => array()
            )
        );
    }
}