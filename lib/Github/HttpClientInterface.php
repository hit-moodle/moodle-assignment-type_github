<?php

/**
 * Performs requests on GitHub API. API documentation should be self-explanatory.
 *
 * @author    Thibault Duplessis <thibault.duplessis at gmail dot com>
 * @license   MIT License
 */
interface Github_HttpClientInterface
{
    /**
     * Send a GET request
     *
     * @param  string   $path            Request path
     * @param  array    $parameters     GET Parameters
     * @param  array    $options        reconfigure the request for this call only
     *
     * @return array                    Data
     */
    public function get($path, array $parameters = array(), array $options = array());

    /**
     * Send a POST request
     *
     * @param  string   $path            Request path
     * @param  array    $parameters     POST Parameters
     * @param  array    $options        reconfigure the request for this call only
     *
     * @return array                    Data
     */
    public function post($path, array $parameters = array(), array $options = array());

    /**
     * Send a HEAD request
     *
     * @param  string   $path            Request path
     * @param  array    $parameters     HEAD Parameters
     * @param  array    $options        reconfigure the request for this call only
     *
     * @return array                    Data
     */
    public function head($path, array $parameters = array(), array $options = array());

    /**
     * Send a PATCH request
     *
     * @param  string   $path            Request path
     * @param  array    $parameters     PATCH Parameters
     * @param  array    $options        reconfigure the request for this call only
     *
     * @return array                    Data
     */
    public function patch($path, array $parameters = array(), array $options = array());

    /**
     * Send a PUT request
     *
     * @param  string   $path            Request path
     * @param  array    $parameters     PUT Parameters
     * @param  array    $options        reconfigure the request for this call only
     *
     * @return array                    Data
     */
    public function put($path, array $parameters = array(), array $options = array());

    /**
     * Send a DELETE request
     *
     * @param  string   $path            Request path
     * @param  array    $parameters     DELETE Parameters
     * @param  array    $options        reconfigure the request for this call only
     *
     * @return array                    Data
     */
    public function delete($path, array $parameters = array(), array $options = array());

    /**
     * Change an option value.
     *
     * @param string $name   The option name
     * @param mixed  $value  The value
     *
     * @return Github_HttpClientInterface The current object instance
     */
    public function setOption($name, $value);
}
