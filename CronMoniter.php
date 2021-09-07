<?php 
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * User class.
 *
 * @extends CI_Controller
 */
class CronMoniter extends CI_Controller {
    /**
     * __construct function.
     *
     * @access public
     * @return void
     */
    public function __construct() {

            // Loading Libraries Here
            parent::__construct();
            $this->load->helper('url');
            $this->load->helper('form');
            $this->load->model('cron_model');
            $this->load->helper('common_helper');
    }

    // Default Area of Tickets Page
    public function index() {

    }
    public function notifyMoniterAlert(){
        $returnArray = $ticketList = $alertDate = array();
        $getAllOwners = $this->cron_model->getAllOwners();
        
        /* Owner Knock*/
        if(!empty($getAllOwners) && is_array($getAllOwners)){
            foreach($getAllOwners as $ownerIndex=>$ownerArray) {
                if($ownerArray['rand_str']!=''){
                    $getAllOrganization = $this->cron_model->getAllOrganization($ownerArray['rand_str']);
                } else {
                    continue;
                }
                $getAllOrgAdmin = $this->cron_model->getAllOrgAdmin($ownerArray['rand_str']);
                /* Org Knock*/
                if(!empty($getAllOrganization) && is_array($getAllOrganization)){
                    foreach($getAllOrganization as $orgIndex=>$orgArray){
                        if($orgArray['id']!='' && $orgArray['id']!=0) {
                            $getAllAlerts = $this->cron_model->getAllAlerts($orgArray['id']);
                        } else {
                            continue;
                        }
                        if(!empty($getAllAlerts) && is_array($getAllAlerts)){
                            foreach($getAllAlerts as $alertIndex=>$alertArray){
                                /* Forward notification alert if eligible */
                                if($alertArray['condition_type']=='unassigned for >'){
                                    $getDays = secondsToTime($alertArray['time_in_seconds']);
                                    $getTickets = $this->cron_model->getUnassignedTickets($orgArray['id'], $ownerArray['rand_str']);
                                    if($getDays['hours']>0){
                                        if(!empty($getTickets) && is_array($getTickets)){
                                            foreach($getTickets as $ticketIndex=>$ticketArray){
                                                $createdSpan = date('Y-m-d H:i:s', strtotime($ticketArray['created_date']." ".$ticketArray['created_time']));
                                                $hoursDiff = hoursDiff($createdSpan);
                                                if($hoursDiff>=$getDays['hours']){
                                                    $ticketList[] = array(
                                                        "id"            => $ticketArray['id'],
                                                        "ticket_no"     => $ticketArray['ticket_no'],
                                                        "summary"       => "Ticket #".$ticketArray['ticket_no']." (".$ticketArray['summary'].")",
                                                        "description"   => " Ticket has been unassigned for longer than ".$hoursDiff." Hours",
                                                        "alertCreated"  => date('M d, Y', strtotime($alertArray['created_date'])),
                                                    );
                                                }
                                            }
                                        }
                                    } else if($getDays['days']>0){
                                        if(!empty($getTickets) && is_array($getTickets)){
                                            foreach($getTickets as $ticketIndex=>$ticketArray){
                                                $createdSpan = date('Y-m-d H:i:s', strtotime($ticketArray['created_date']." ".$ticketArray['created_time']));
                                                $daysDiff   = daysDiff($createdSpan);
                                                if($daysDiff>=$getDays['days']){
                                                    $ticketList[] = array(
                                                        "id"            => $ticketArray['id'],                                                        
                                                        "ticket_no"     => $ticketArray['ticket_no'],
                                                        "summary"       => "Ticket #".$ticketArray['ticket_no']." (".$ticketArray['summary'].")",
                                                        "description"   => " Ticket has been unassigned for longer than ".$daysDiff." Days",
                                                        "alertCreated"  => date('M d, Y', strtotime($alertArray['created_date'])),
                                                    );
                                                }
                                            }
                                        }
                                    }
                                }
                                $alertDate[] = date('Y-m-d', $alertArray['created_date']);
                                $minDate     = date('Y-m-d', $alertArray['created_date']);
                            }
                        }
                        usort($alertDate, function($a, $b) {
                            $dateTimestamp1 = strtotime($a);
                            $dateTimestamp2 = strtotime($b);

                            return $dateTimestamp1 < $dateTimestamp2 ? -1: 1;
                        });
                
                        /* Triggering alert mail to all admin*/
                        if(!empty($ticketList) && is_array($ticketList)){
                            if(!empty($getAllOrgAdmin) && is_array($getAllOrgAdmin)){
                                $data['ticketList'] = $ticketList;
                                $data['minAlert']   =  ($alertDate[0]) ? $alertDate[0] : $minDate;
                                $data['maxAlert']   = ($alertDate[count($alertDate) - 1]) ? $alertDate[count($alertDate) - 1] : 0;
                                $alertMessage = $this->load->view('mail_templates/moniter_alert', $data, true);
                                foreach($getAllOrgAdmin as $key=>$adminArray){
                                    //$this->load->library('email', $this->config->config['config_mail']);
									$this->load->library('email');
									$config = array (
									'mailtype' => 'html',
									'charset'  => 'utf-8',
									'priority' => '1',
									'bcc_batch_mode'    =>  TRUE,
									);
									$this->email->initialize($config);
                                    $this->email->from($orgArray['email'], $orgArray['name']." Help Desk");
                                    $this->email->subject("Notifications from Spiceworks for eorchid");
                                    $this->email->message($alertMessage);
                                    $this->email->to($adminArray['email']);
                                    $this->email->send();
                                    $this->email->clear();
                                }
                            }
                            /* Update tickets were notified */
                            foreach($ticketList as $ticketIndex=>$updateTicket){
                                $updateArray = array(
                                    "notified_date"   => date('Y-m-d'),
                                    "notified_time"   => date('H:i:s'),
                                    "notified_status" => 1,
                                );
                                // $this->cron_model->ticketUpdate($updateTicket['id'], $updateArray);
                            }
                        }
                    }
                }
            }
        }
        return $returnArray;
    }

}
?>