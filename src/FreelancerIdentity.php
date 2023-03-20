<?php

namespace Sydefz\OAuth2\Client\Provider;

use Exception;
use BadMethodCallException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use League\OAuth2\Client\Grant\Exception\InvalidGrantException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

class FreelancerIdentity extends AbstractProvider {
    /**
     * scopes, prompt and advanced_scopes can be modified by
     * $options array on construct
     */
    public $scopes = ['basic'];
    public $prompt = ['select_account', 'consent'];
    public $advancedScopes = [];

    private $separator = ' ';
    private $responseCode = 'error_code';
    private $responseError = 'message';
    private $ownerId = 'email';

    /**
     * @throws FreelancerIdentityException when no baseUri option
     */
    public function __construct($options = []) {
        parent::__construct($options);

        $this->prepareAdvancedScopes();
        if (isset($options['sandbox']) && $options['sandbox']) {
            $this->baseUri = 'https://accounts.freelancer-sandbox.com';
            $this->apiBaseUri = 'https://www.freelancer-sandbox.com/api';
        } else {
            $this->baseUri = 'https://accounts.freelancer.com';
            $this->apiBaseUri = 'https://api.freelancer.com/api';
        }
    }

    /**
     * @throws FreelancerIdentityException on invalid grant or options
     */
    public function getAccessToken($grant, array $options = []) {
        try {
            $grant = $this->verifyGrant($grant);

            $params = [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri'  => $this->redirectUri,
            ];

            $params = $grant->prepareRequestParameters($params, $options);
            $request = $this->getAccessTokenRequest($params);
            $response = $this->getResponse($request);
            $this->accessTokenArray = $this->prepareAccessTokenResponse($response);
            $this->accessToken = $this->createAccessToken($this->accessTokenArray, $grant);

            return $this->accessToken;
        } catch (BadMethodCallException $e) {
            throw new FreelancerIdentityException($e->getMessage());
        } catch (InvalidGrantException $e) {
            throw new FreelancerIdentityException('Invalid grant type.');
        } catch (InvalidArgumentException $e) {
            throw new FreelancerIdentityException($e->getMessage());
        } catch (Exception $e) {
            throw new FreelancerIdentityException('Unknown error occurred.');
        }
    }

    public function setAccessTokenFromArray(array $accessToken) {
        try {
            $this->accessToken = new AccessToken($accessToken);
        } catch (InvalidArgumentException $e) {
            throw new FreelancerIdentityException($e->getMessage());
        }
    }

    public function getAuthenticatedRequest($method, $url, array $options = []) {
        if (!isset($this->accessToken)) {
            throw new FreelancerIdentityException('No access token set.');
        }
        return $this->createRequest($method, $url, $this->accessToken, $options);
    }

    /**
     * Returns the default scopes used by this provider
     * unless specific scopes pass in on constructor
     *
     * @return array
     */
    public function getDefaultScopes() {
        return $this->scopes;
    }

    public function getResourceOwner() {
        $response = $this->fetchResourceOwnerDetails();
        return $this->createResourceOwner($response, $this->accessToken);
    }

    public function getBaseAuthorizationUrl() {
        return $this->baseUri.'/oauth/authorise';
    }

    public function getBaseAccessTokenUrl(array $params) {
        return $this->baseUri.'/oauth/token';
    }

    public function getResourceOwnerDetailsUrl(\League\OAuth2\Client\Token\AccessToken $token) {
        return $this->baseUri.'/oauth/me';
    }

    /**
     * by default user needs to select account then consent on requested scopes
     * explicitly define it in prompt will force user to go through these steps
     *
     * select_account step is optional and can be skip by remove it from prompt
     * consent step is optional if client is white-listed (FLN, WAR, Escrow) or
     * bearer token exist and all scopes granted previously
     *
     * advanced scopes is required if you want to use freelancer API on user's behave
     * you will need to select advanced scopes when you create your app
     *
     * @return array
     */
    protected function getAuthorizationParameters(array $options) {
        $options = parent::getAuthorizationParameters($options);
        $options += ['prompt' => implode($this->separator, $this->prompt)];
        $options += ['advanced_scopes' => implode($this->separator, $this->advancedScopes)];
        return $options;
    }

    protected function getScopeSeparator() {
        return $this->separator;
    }

    /**
     * all requests made by getAuthenticatedRequest() with $token passed in
     * will have bearer token set in their headers
     */
    protected function getAuthorizationHeaders($token = null) {
        if ($token) {
            return [
                'Authorization' => 'Bearer '.$token->getToken(),
                'Freelancer-OAuth-V1' => $token->getToken(),
            ];
        } else {
            return [];
        }
    }

    /**
     * @throws FreelancerIdentityException when response contains error/exception
     */
    protected function checkResponse(ResponseInterface $response, $data) {
        if (isset($data[$this->responseError])) {
            // wrap the repsonse error code into exception as exception code
            if (isset($data[$this->responseCode])) {
                $errorCodeId = FreelancerIdentityException::ERROR_MAPPING[$data[$this->responseCode]];
            }
            throw new FreelancerIdentityException($data[$this->responseError], $errorCodeId);
        }
    }

    protected function fetchResourceOwnerDetails() {
        $url = $this->getResourceOwnerDetailsUrl($this->accessToken);
        $request = $this->getAuthenticatedRequest(self::METHOD_GET, $url);
        return $this->getResponse($request);
    }

    protected function createResourceOwner(array $response, AccessToken $token) {
        return new GenericResourceOwner($response, $response[$this->ownerId]);
    }

    protected function prepareAdvancedScopes() {
        $mapping = FreelancerIdentityAdvancedScope::MAPPING;
        foreach ($this->advancedScopes as &$advancedScope) {
            if (!is_numeric($advancedScope)) {
                if (isset($mapping[$advancedScope])) {
                    $advancedScope = $mapping[$advancedScope];
                } else {
                    throw new FreelancerIdentityException('Invalid advanced scope given.');
                }

            }
        }
    }
}

class FreelancerIdentityException extends Exception {
    const ERROR_MAPPING = [
        'INVALID_ATTRIBUTE' => 0,
        'MISSING_REQUIRED_FIELDS' => 1,
        'INTEGRITY_ERROR' => 2,
        'UNAUTHORISED_ACTION' => 3,
        'ORGANISATION_ALREADY_EXISTS' => 4,
        'MISSING_FIELDS' => 5,
        'UNKNOWN_ERROR' => 6,
        'NOT_FOUND' => 7,
        'INVALID_CLAUSE' => 8,
        'DUPLICATE_ENTRY' => 9,
    ];
}

class FreelancerIdentityAdvancedScope {
    const MAPPING = [
        'fln:project_create' => 1,
        'fln:project_manage' => 2,
        'fln:contest_create' => 3,
        'fln:contest_manage' => 4,
        'fln:messaging' => 5,
        'fln:user_information' => 6,
    ];
}
