<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Braintree extends Gene_Braintree_Model_Wrapper_Braintree
{
    /**
     * Generate a server side token with the specified account ID
     *
     * @return mixed
     */
    public function generateToken($userId, $test)
    {
        if ($userId) {
            $data = array(
                "merchantAccountId" => $this->getMerchantAccountId(),
                'customerId' => $userId,
                'options' => array(
                    'makeDefault' => true,
                    'verifyCard' => true,
                ),
                'version' => 3
            );
            if ($test) {
                $data['version'] = 2;
            }
            // Use the class to generate the token
            return Braintree_ClientToken::generate($data);
        }

        return Braintree_ClientToken::generate(
            array(
                "merchantAccountId" => $this->getMerchantAccountId(),
            )
        );
    }
}