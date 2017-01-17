<?php namespace Calverley\Asterisk;

class Asterisk
{
    private $config;

    private $manager;

    private $ari;

    public function __construct($config)
    {
        $this->config = $config;
        $this->manager = new Manager($config);
        $this->manager->connect();
        $this->ari = new Ari();
    }

    public function __destruct()
    {
        $this->manager->disconnect();
    }

    /**
     * Return All Queues
     *
     * @return mixed
     */
    public function Queues()
    {
        $queues = $this->manager->Command('queue show')['data'];
        $lines = explode(PHP_EOL, $queues);
        $lines[0] = str_replace("\r", '', $lines[0]);
        $nextlinemember = FALSE;
        $nextlinecaller = FALSE;
        $currentqueue = '';
        $membercount = 0;
        $callercount = 0;
        foreach($lines as $line){
            if($line == $lines[0]) continue;
            elseif($line === "") continue;
            elseif($line[0] != ' '){
                $nextlinecaller = FALSE;
                $callercount = 0;
                $queueinfo = $this->multiexplode(array(' ',',',':'), $line);
                $currentqueue = $queueinfo[0];
                $data[$currentqueue]['Current Calls'] = $queueinfo[2];
                $data[$currentqueue]['Answered Calls'] = $queueinfo[19];
                $data[$currentqueue]['Unanswered Calls'] = $queueinfo[22];
                $data[$currentqueue]['Service Level'] = $queueinfo[25];
                $data[$currentqueue]['Extensions'] = array();
                $data[$currentqueue]['Callers'] = array();
            }elseif(strpos($line, 'Members:') !== FALSE){
                $nextlinemember = TRUE;
                continue;
            }elseif(strpos($line, 'Callers') !== FALSE){
                $nextlinemember = FALSE;
                $membercount = 0;
            }elseif($nextlinemember){
                $line = str_replace('taken ', "/", $line);
                $line = str_replace(' call', '/', $line);
                $memberinfo = $this->multiexplode(array('(','/','@',')'), $line);
                $data[$currentqueue]['Extensions'][$membercount]['Extension'] = $memberinfo[2];
                $data[$currentqueue]['Extensions'][$membercount]['Name'] = trim($memberinfo[0]);
                if($memberinfo[13] == 'no') $data[$currentqueue]['Extensions'][$membercount]['Calls Taken'] = '0';
                else $data[$currentqueue]['Extensions'][$membercount]['Calls Taken'] = $memberinfo[13];
                $data[$currentqueue]['Extensions'][$membercount]['Status'] = $memberinfo[11];
                $membercount++;
                continue;
            }
            if(strpos($line, 'Callers:') !== FALSE){
                $nextlinecaller = TRUE;
                continue;
            }elseif($nextlinecaller){
                $callerinfo = $this->multiexplode(array('+','-',' ',':',',','.'), $line);
                $data[$currentqueue]['Callers'][$callercount]['Caller Number'] = $callerinfo[6];
                $data[$currentqueue]['Callers'][$callercount]['Caller ID'] = $callerinfo[9];
                $data[$currentqueue]['Callers'][$callercount]['Called At'] = $this->getTimeCalled($callerinfo[13], $callerinfo[14]);
                $callercount++;
            }
        }
        return $data;

    }

    /**
     * Return Specific Queue
     *
     * @param $id
     * @return string
     */
    public function Queue($queue)
    {
        $queues = $this->Queues();
        if(isset($queues[$queue])) return $queues[$queue];
        else return 'Queue not found';
    }

    /**
     * Return Extensions Logged Into Queue
     *
     * @param $id
     * @return string
     */
    public function QueueExtensions($queue)
    {
        $queues = $this->Queue($queue);
        if($queues == 'Queue not found') return $queues;
        return $queues['Extensions'];
    }

    /**
     * Return Specific Extension in Specific Queue
     *
     * @param $queue
     * @param $extension
     * @return string
     */
    public function QueueExtension($queue, $extension)
    {
        $extensions = $this->QueueExtensions($queue);
        if($extensions == 'Queue not found') return $extensions;
        foreach($extensions as $member){
            if($member['Extension'] == $extension) return $member;
        }
        return 'Extension not found';
    }

    /**
     * Return Callers in Specific Queue
     *
     * @param $queue
     * @return string
     */
    public function QueueCalls($queue)
    {
        $queues = $this->Queue($queue);
        if($queues == 'Queue not found') return $queues;
        return $queues['Callers'];
    }

    /**
     * Return Specific Caller Within Specific Queue
     *
     * @param $queue
     * @param $callnumber
     * @return string
     */
    public function QueueCall($queue, $callnumber)
    {
        $calls = $this->QueueCalls($queue);
        if($calls == 'Queue not found') return $calls;
        foreach($calls as $call){
            if($call['Caller Number'] == $callnumber) return json_encode($call);
        }
        return 'Caller Number not found';
    }

    /**
     * Return All Extensions
     *
     * @return array
     */
    public function Extensions()
    {
        $ariextensions = $this->ari->getExtensions();
        $ariextensions = json_decode($ariextensions);
        $i = 0;
        $extensions = array();
        foreach($ariextensions as $extension){
            $extensions[$i] = $this->manager->ExtensionState($extension->resource, 'default');
            unset($extensions[$i]['Response']);
            unset($extensions[$i]['Message']);
            unset($extensions[$i]['Context']);
            unset($extensions[$i]['Hint']);
            $extensions[$i]['Extension'] = $extensions[$i]['Exten'];
            unset($extensions[$i]['Exten']);
            $extensions[$i]['Status'] = $extensions[$i]['StatusText'];
            unset($extensions[$i]['StatusText']);
            $i++;
        }
        return $extensions;
    }

    /**
     * Return Specific Extension
     *
     * @param $extension
     * @return string
     */
    public function Extension($extension)
    {
        $extensions = $this->Extensions();
        foreach($extensions as $member){
            if($member['Extension'] == $extension) return $member;
        }
        return 'Extension not found';
    }

    /**
     * Return Callers for specific extension
     *
     * @param $extension
     * @return array|string
     */
    public function ExtensionCalls($extension)
    {
        $ariextensions = json_decode($this->ari->getExtensions());
        $foundextension = FALSE;
        foreach($ariextensions as $ariextension){
            if($ariextension->resource == $extension){
                $foundextension = TRUE;
                $channels = $ariextension->channel_ids;
            }
        }
        if(!$foundextension) return 'Extension not found';
        $callers = array();
        $i = 0;
        foreach($channels as $channel){
            $callers[$i] = json_decode($this->ari->getChannel($channel));
            $i++;
        }
        return $callers;
    }

    /**
     * Return Specific Call for Specific Extension
     *
     * @param $extension
     * @param $channelid
     * @return array|string
     */
    public function ExtensionCall($extension, $channelid)
    {
        $callers = $this->ExtensionCallers($extension);
        if($callers == 'Extension not found') return $callers;
        foreach($callers as $caller){
            if($caller->id == $channelid) return json_encode($caller);
        }
        return 'Channel Id not found';
    }

    /**
     * helper method for parsing with multiple delimiters
     *
     * @param $delimiters
     * @param $string
     * @return array
     */
    public function multiexplode ($delimiters,$string) {

        $ready = str_replace($delimiters, $delimiters[0], $string);
        $launch = explode($delimiters[0], $ready);
        return  $launch;
    }

    /**
     * helper method to subtract wait time from current time
     *
     * parameters are the wait time
     *
     * @param $minutes
     * @param $seconds
     * @return string
     */
    public function getTimeCalled($minutes, $seconds)
    {
        date_default_timezone_set("America/Los_Angeles");
        $time = date('H:i:s');
        $time = explode(':', $time);
        if($time[2] < $seconds){
            $time[1]--;
            $time[2] = 60 - ($seconds - $time[2]);
        }else{
            $time[2] = $time[2] - $seconds;
        }
        if($time[1] < $minutes){
            $time[0]--;
            $time[1] = 60 - ($minutes - $time[1]);
        }else{
            $time[1] = $time[1] - $minutes;
        }
        if($time[0] < 0){
            $time[0] = 24 + $time[0];
        }
        $i = 0;
        while($i < 3){
            if($time[$i] < 10)
                $time[$i] = '0'.$time[$i];
            $i++;
        }
        return $time[0].':'.$time[1].':'.$time[2];
    }

}