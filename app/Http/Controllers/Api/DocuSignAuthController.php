<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Request;
use DocuSign\eSign\Configuration;
use DocuSign\eSign\Api\EnvelopesApi;
use DocuSign\eSign\Client\ApiClient;
use DocuSign\eSign\Model\EnvelopeDefinition;
use DocuSign\eSign\Model\TemplateRole;
use GuzzleHttp\Client as GuzzleClient;


use Exception;
use Session;


class DocuSignAuthController extends Controller   
{
    private $config;

    private $signer_client_id = 1000; # Used to indicate that the signer will use embedded

    /** Specific template arguments */
    private $args;


    public function docusignRedirect()
    {
        $clientId = 'eb6e5274-c787-4749-8add-78a5c64429fb'; // Your DocuSign integration key
    //    $redirectUri = 'http://localhost:8000/api/auth/docusign/callback'; // Your redirect URI
    //  $redirectUri = 'https://essdev.evacare.com';
    $redirectUri = ' http://localhost:5173/ptocashout';

        $authorizationUrl = 'https://account-d.docusign.com/oauth/auth?' . http_build_query([
            'response_type' => 'code',
            'scope' => 'cors signature',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
        ]);

        return redirect()->away($authorizationUrl);
    }

    public function docusignCallback(Request $request)
    {
        $code = $request->input('code');

        $accessToken = $this->exchangeAuthorizationCode($code);

        return response()->json(['access_token' => $accessToken]);
   
    }

    private function exchangeAuthorizationCode($code)
    {
        $clientId = 'eb6e5274-c787-4749-8add-78a5c64429fb'; // Your DocuSign integration key
        $clientSecret = '8dade108-6615-493f-9bb9-be59f7d22c5d'; // Your DocuSign client secret
       //  $redirectUri = 'http://localhost:8000/api/auth/docusign/callback'; // Your redirect URI
        // $redirectUri = 'https://essdev.evacare.com';
        $redirectUri = 'http://localhost:5173/ptocashout';
       

        $client = new Client();
        $response = $client->post('https://account-d.docusign.com/oauth/token', [
            'form_params' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
            // Add the scope parameter here
            ],
            'curl' => [
                CURLOPT_SSL_VERIFYPEER => false,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        session()->put('docusign_auth_code', $data['access_token']);
        return $data['access_token'];
    }

    public function signDocument()
    {       
        try{
            $this->args = $this->getTemplateArgs();

        $args = $this->args;
       

        $envelope_args = $args["envelope_args"];
        
        # Create the envelope request object
        $envelope_definition = $this->make_envelope($args["envelope_args"]);
        $envelope_api = $this->getEnvelopeApi();
        # Call Envelopes::create API method
        # Exceptions will be caught by the calling function

        $api_client = new \DocuSign\eSign\client\ApiClient($this->config);
        $envelope_api = new \DocuSign\eSign\Api\EnvelopesApi($api_client);
        $results = $envelope_api->createEnvelope($args['account_id'], $envelope_definition);
        $envelope_id = $results->getEnvelopeId();

        $authentication_method = 'None'; # How is this application authenticating
        # the signer? See the `authenticationMethod' definition
        # https://developers.docusign.com/esign-rest-api/reference/Envelopes/EnvelopeViews/createRecipient
        $recipient_view_request = new \DocuSign\eSign\Model\RecipientViewRequest([
            'authentication_method' => $authentication_method,
            'client_user_id' => $envelope_args['signer_client_id'],
            'recipient_id' => '1',
            'return_url' => $envelope_args['ds_return_url'],
            'user_name' => 'shaiv', 'email' => 'shaivroy1@gmail.com'
        ]);

        $results = $envelope_api->createRecipientView($args['account_id'], $envelope_id,$recipient_view_request);

        return redirect()->to($results['url']);
        } catch (Exception $e) {
            dd($e);
        }
        
    }

    private function make_envelope($args)
    {   
        
        $filename = 'World_Wide_Corp_lorem.pdf';

        $demo_docs_path = asset('doc/'.$filename);

        $arrContextOptions=array(
            "ssl"=>array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            ),
        );  

        $content_bytes = file_get_contents($demo_docs_path,false, stream_context_create($arrContextOptions));
        // dd($content_bytes);
        $base64_file_content = base64_encode($content_bytes);
        // dd($base64_file_content);
        # Create the document model
        $document = new \DocuSign\eSign\Model\Document([# create the DocuSign document object
        'document_base64' => $base64_file_content,
            'name' => 'Example document', # can be different from actual file name
            'file_extension' => 'pdf', # many different document types are accepted
            'document_id' => 1, # a label used to reference the doc
        ]);
        # Create the signer recipient model
        $signer = new \DocuSign\eSign\Model\Signer([# The signer
        'email' => 'shaivroy1@gmail.com', 'name' => 'shaiv',
            'recipient_id' => "1", 'routing_order' => "1",
            # Setting the client_user_id marks the signer as embedded
            'client_user_id' => $args['signer_client_id'],
        ]);
        # Create a sign_here tab (field on the document)
        $sign_here = new \DocuSign\eSign\Model\SignHere([# DocuSign SignHere field/tab
        'anchor_string' => '/sn1/', 'anchor_units' => 'pixels',
            'anchor_y_offset' => '10', 'anchor_x_offset' => '20',
        ]);
        # Add the tabs model (including the sign_here tab) to the signer
        # The Tabs object wants arrays of the different field/tab types
        $signer->settabs(new \DocuSign\eSign\Model\Tabs(['sign_here_tabs' => [$sign_here]]));
        # Next, create the top level envelope definition and populate it.

        $envelope_definition = new \DocuSign\eSign\Model\EnvelopeDefinition([
            'email_subject' => "Please sign this document sent from the CodeHunger",
            'documents' => [$document],
            # The Recipients object wants arrays for each recipient type
            'recipients' => new \DocuSign\eSign\Model\Recipients(['signers' => [$signer]]),
            'status' => "sent", # requests that the envelope be created and sent.
        ]);

        return $envelope_definition;
    }

    /**
     * Getter for the EnvelopesApi
     */
    public function getEnvelopeApi(): EnvelopesApi
    {   
        $this->config = new Configuration();
        $this->config->setHost($this->args['base_path']);
        $this->config->addDefaultHeader('Authorization', 'Bearer ' . $this->args['ds_access_token']);    
        $this->apiClient = new ApiClient($this->config);

        return new EnvelopesApi($this->apiClient);
    }

    /**
     * Get specific template arguments
     *
     * @return array
     */
    private function getTemplateArgs()
    {   
        $envelope_args = [
            'signer_client_id' => $this->signer_client_id,
            'ds_return_url' => route('docusign')
        ];
        $args = [
            'account_id' => env('DOCUSIGN_ACCOUNT_ID'),
            'base_path' => env('DOCUSIGN_BASE_URL'),
            'ds_access_token' => Session::get('docusign_auth_code'),
            'envelope_args' => $envelope_args
        ];
        
        return $args;
        
    }
    
    
}
