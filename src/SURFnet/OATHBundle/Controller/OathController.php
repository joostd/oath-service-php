<?php

namespace SURFnet\OATHBundle\Controller;

use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Controller\Annotations\Get;

class OathController extends BaseController
{
    /**
     * @Get("/oath/challenge/ocra")
     * @ApiDoc(
     *  section="OATH",
     *  description="Get an OCRA challenge",
     *  statusCodes={
     *      200="Success, challenge is in the body",
     *      401="Invalid consumer key",
     *      500="General error, something went wrong",
     *  },
     * )
     */
    public function getOcraChallengeAction()
    {
        $responseCode = 200;
        try {
            $this->verifyConsumerKey();
            $oathservice = $this->getOATHService('ocra');
            $data = $oathservice->generateChallenge();
        } catch (\Exception $e) {
            $data = array('error' => $e->getMessage());
            $responseCode = $e->getCode() ?: 500;
        }
        $view = $this->view($data, $responseCode);
        return $this->handleView($view);
    }

    /**
     * @Get("/oath/validate/ocra")
     * @ApiDoc(
     *  section="OATH",
     *  description="Validate a OCRA challenge against a response",
     *  parameters={
     *    {"name"="challenge", "dataType"="string", "required"=true, "description"="The original challenge generated by GET /oath/challenge/ocra"},
     *    {"name"="response", "dataType"="string", "required"=true, "description"="The response to validate the challenge against"},
     *    {"name"="secret", "dataType"="string", "required"=true, "description"="The secret"},
     *    {"name"="sessionKey", "dataType"="string", "required"=true, "description"="The session key"}
     *  },
     *  statusCodes={
     *      204="Success, challenge is valid",
     *      400="Challenge is not valid",
     *      401="Invalid consumer key",
     *      500="General error, something went wrong",
     *  },
     * )
     */
    public function validateOcraChallengeAction()
    {
        $request = $this->get('request_stack')->getCurrentRequest();
        return $this->validateChallenge('ocra', $request->get('response'), $request->get('challenge'), $request->get('secret'), $request->get('sessionKey'));
    }

    /**
     * @Get("/oath/validate/hotp")
     * @ApiDoc(
     *  section="OATH",
     *  description="Validate a HOTP challenge against a response",
     *  parameters={
     *    {"name"="challenge", "dataType"="string", "required"=true, "description"="The original challenge generated by GET /oath/challenge/hotp"},
     *    {"name"="response", "dataType"="string", "required"=true, "description"="The response to validate the challenge against"},
     *  },
     *  statusCodes={
     *      204="Success, challenge is valid",
     *      400="Challenge is not valid",
     *      401="Invalid consumer key",
     *      500="General error, something went wrong",
     *  },
     * )
     */
    public function validateHotpChallengeAction()
    {
        $request = $this->get('request_stack')->getCurrentRequest();
        return $this->validateChallenge('hotp', $request->get('response'), $request->get('challenge'));
    }

    /**
     * @Get("/oath/validate/totp")
     * @ApiDoc(
     *  section="OATH",
     *  description="Validate a TOTP challenge against a response",
     *  parameters={
     *    {"name"="challenge", "dataType"="string", "required"=true, "description"="The original challenge generated by GET /oath/challenge/totp"},
     *    {"name"="response", "dataType"="string", "required"=true, "description"="The response to validate the challenge against"},
     *  },
     *  statusCodes={
     *      204="Success, challenge is valid",
     *      400="Challenge is not valid",
     *      401="Invalid consumer key",
     *      500="General error, something went wrong",
     *  },
     * )
     */
    public function validateTotpChallengeAction()
    {
        $request = $this->get('request_stack')->getCurrentRequest();
        return $this->validateChallenge('ocra', $request->get('response'), $request->get('challenge'));
    }

    /**
     * Validate the challenge against the given response
     *
     * @param string $type
     * @param string $response
     * @param string $challenge
     * @param string $secret
     * @param string $sessionKey
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function validateChallenge($type, $response, $challenge, $secret = null, $sessionKey = null)
    {
        $responseCode = 204;
        $data = null;
        try {
            $this->verifyConsumerKey();
            $oathservice = $this->getOATHService($type);
            $result = $oathservice->validateResponse($response, $challenge, $secret, $sessionKey);
            if (!$result) {
                $responseCode = 400;
            }
        } catch (\Exception $e) {
            $data = array('error' => $e->getMessage(), 'trace' => $e->getTraceAsString(),);
            $responseCode = $e->getCode() ?: 500;
        }
        $view = $this->view($data, $responseCode);
        return $this->handleView($view);
    }

    /**
     * Create the storage class using the storage factory and return the class
     *
     * @param string $type
     *
     * @return mixed
     */
    protected function getOATHService($type)
    {
        $oathFactory = $this->get('surfnet_oath.oath.factory');
        $config = $this->container->getParameter('surfnet_oath');
        return $oathFactory->createOATHService($type, (isset($config['oath'][$type]) ? $config['oath'][$type] : array()));
    }
}