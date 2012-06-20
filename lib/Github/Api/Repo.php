<?php

/**
 * Searching repositories, getting repository information
 * and managing repository information for authenticated users.
 *
 * @link      http://develop.github.com/p/repos.html
 * @author    Thibault Duplessis <thibault.duplessis at gmail dot com>
 * @license   MIT License
 */
class Github_Api_Repo extends Github_Api
{
    /**
     * Search repos by keyword
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $query            the search query
     * @param   string  $language         takes the same values as the language drop down on http://github.com/search
     * @param   int     $startPage        the page number
     * @return  array                     list of repos found
     */
    public function search($query, $language = '', $startPage = 1)
    {
        $response = $this->get('legacy/repos/search/'.urlencode($query), array(
            'language' => strtolower($language),
            'start_page' => $startPage
        ));

        return $response['repositories'];
    }

    /**
     * Get the repositories of a user
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the username
     * @return  array                     list of the user repos
     */
    public function getUserRepos($username)
    {
        $response = $this->get('users/'.urlencode($username).'/repos');

        return $response;
    }

    /**
     * Get a list of the repositories that the authenticated user can push to
     *
     * @return  array                     list of repositories
     */
    public function getPushableRepos()
    {
        $response = $this->get('user/repos', array('type' => 'member'));

        return $response;
    }

    /**
     * Get extended information about a repository by its username and repo name
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @return  array                     informations about the repo
     */
    public function show($username, $repo)
    {
        $response = $this->get('repos/'.urlencode($username).'/'.urlencode($repo));

        return $response;
    }

    /**
     * create repo
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $name             name of the repository
     * @param   string  $description      repo description
     * @param   string  $homepage         homepage url
     * @param   bool    $public           1 for public, 0 for private
     * @return  array                     returns repo data
     */
    public function create($name, $description = '', $homepage = '', $private = false, $organization = '')
    {
        $parameters = array(
            'name' => $name,
            'description' => $description,
            'homepage' => $homepage,
            'private' => $private
        );

        if ($organization) {
            $response = $this->post('orgs/'.urlencode($organization).'/repos', $parameters);
        } else {
            $response = $this->post('user/repos', $parameters);
        }

        return $response;
    }

    /**
     * delete repo
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             name of the repository
     *
     * @return  string|array              returns delete_token or repo status
     */
    public function delete($username, $repo)
    {
        $response = $this->delete('repos/'.urlencode($username).'/'.urlencode($repo));
    }

    /**
     * Set information of a repository
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @param   array   $values           the key => value pairs to post
     * @return  array                     informations about the repo
     */
    public function setRepoInfo($username, $repo, $values)
    {
        $response = $this->patch('repos/'.urlencode($username).'/'.urlencode($repo), $values);

        return $response;
    }

    /**
     * Set the visibility of a repostory to public
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @return  array                     informations about the repo
     */
    public function setPublic($username, $repo)
    {
        return $this->setRepoInfo($username, $repo, array('private' => false));
    }

    /**
     * Set the visibility of a repostory to private
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @return  array                     informations about the repo
     */
    public function setPrivate($username, $repo)
    {
        return $this->setRepoInfo($username, $repo, array('private' => true));
    }

    /**
     * Get the list of deploy keys for a repository
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @return  array                     the list of deploy keys
     */
    public function getDeployKeys($username, $repo)
    {
        $response = $this->get('repos/'.urlencode($username).'/'.urlencode($repo).'/keys');

        return $response;
    }

    /**
     * Add a deploy key for a repository
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @param   string  $title            the title of the key
     * @param   string  $key              the public key data
     * @return  array                     the list of deploy keys
     */
    public function addDeployKey($username, $repo, $title, $key)
    {
        $response = $this->post('repos/'.urlencode($username).'/'.urlencode($repo).'/keys', array(
            'title' => $title,
            'key' => $key
        ));

        return $response;
    }

    /**
     * Delete a deploy key from a repository
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @param   string  $id               the the id of the key to remove
     * @return  array                     the list of deploy keys
     */
    public function removeDeployKey($username, $repo, $id)
    {
        $response = $this->delete('repos/'.urlencode($url).'/'.urlencode($repo).'/keys/'.urlencode($id));

        return $response;
    }

    /**
     * Get the collaborators of a repository
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @return  array                     list of the repo collaborators
     */
    public function getRepoCollaborators($username, $repo)
    {
        $response = $this->get('repos/'.urlencode($username).'/'.urlencode($repo).'/collaborators');

        return $response;
    }

    /**
     * Add a collaborator to a repository
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @param   string  $user             the user who should be added as a collaborator
     * @return  array                     list of the repo collaborators
     */
    public function addRepoCollaborator($username, $repo, $user)
    {
        $response = $this->put('repos/'.urlencode($username).'/'.urlencode($repo).'/collaborators/'.urlencode($user));

        return $response;
    }

    /**
     * Delete a collaborator from a repository
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @param   string  $user             the user who should be removed as a collaborator
     * @return  array                     list of the repo collaborators
     */
    public function removeRepoCollaborator($username, $repo, $user)
    {
        $response = $this->delete('repos/'.urlencode($username).'/'.urlencode($repo).'/collaborators/'.urlencode($user));

        return $response;
    }

    /**
     * Make the authenticated user watch a repository
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @return  array                     informations about the repo
     */
    public function watch($username, $repo)
    {
        $response = $this->put('user/watched/'.urlencode($username).'/'.urlencode($repo));

        return $response;
    }

    /**
     * Make the authenticated user unwatch a repository
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @return  array                     informations about the repo
     */
    public function unwatch($username, $repo)
    {
        $response = $this->delete('user/watched/'.urlencode($username).'/'.urlencode($repo));

        return $response;
    }

    /**
     * Make the authenticated user fork a repository
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @param   string  $organization     the repository will be forked into this organization
     * @return  array                     informations about the newly forked repo
     */
    public function fork($username, $repo, $organization = '')
    {
        if ($organization) {
            $response = $this->post('repos/'.urlencode($username).'/'.urlencode($repo).'/forks', array('org' => $organization));
        } else {
            $response = $this->post('repos/'.urlencode($username).'/'.urlencode($repo).'/forks');
        }

        return $response;
    }

    /**
     * Get the tags of a repository
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @return  array                     list of the repo tags
     */
    public function getRepoTags($username, $repo)
    {
        $response = $this->get('repos/'.urlencode($username).'/'.urlencode($repo).'/tags');

        return $response;
    }

    /**
     * Get the branches of a repository
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the username
     * @param   string  $repo             the name of the repo
     * @return  array                     list of the repo branches
     */
    public function getRepoBranches($username, $repo)
    {
        $response = $this->get('repos/'.urlencode($username).'/'.urlencode($repo).'/branches');

        return $response;
    }

    /**
     * Get the watchers of a repository
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @return  array                     list of the repo watchers
     */
    public function getRepoWatchers($username, $repo)
    {
        $response = $this->get('repos/'.urlencode($username).'/'.urlencode($repo).'/watchers');

        return $response;
    }

    /**
     * Get the network (a list of forks) of a repository
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @return  array                     list of the repo forks
     */
    public function getRepoNetwork($username, $repo)
    {
        $response = $this->get('repos/'.urlencode($username).'/'.urlencode($repo).'/forks');

        return $response;
    }

    /**
     * Get the language breakdown of a repository
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @return  array                     list of the languages
     */
    public function getRepoLanguages($username, $repo)
    {
        $response = $this->get('repos/'.urlencode($username).'/'.urlencode($repo).'/languages');

        return $response;
    }

    /**
     * Get the contributors of a repository
     * http://develop.github.com/p/repo.html
     *
     * @param   string  $username         the user who owns the repo
     * @param   string  $repo             the name of the repo
     * @param   boolean $includingNonGithubUsers by default, the list only shows GitHub users. You can include non-users too by setting this to true
     * @return  array                     list of the repo contributors
     */
    public function getRepoContributors($username, $repo, $includingNonGithubUsers = false)
    {
        $url = 'repos/'.urlencode($username).'/'.urlencode($repo).'/contributors';
        if ($includingNonGithubUsers) {
            $response = $this->get($url, array('anon' => true));
        } else {
            $response = $this->get($url);
        }

        return $response;
    }

}
