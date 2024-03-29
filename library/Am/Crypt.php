<?php

/**
 * @package Am_Crypt
 */
class Am_Exception_Crypt extends Am_Exception_InternalError
{

}

class Am_Exception_Crypt_Key extends Am_Exception_Crypt
{

}

/**
 * @package Am_Crypt
 */
abstract
    class Am_Crypt
{

    protected
        $key;



    function __construct($key = null)
    {
        $this->key = $key;
    }

    abstract
        function encrypt($s);

    abstract
        function decrypt($s);

    abstract
        function getKeySignature();

    static
        function getMethod()
    {
        return lcfirst(substr(get_called_class(), 9));
    }


    /**
     *
     * @param String $signature  - encryption method, or key signature.
     * @return type
     */
    static
        function getClassByMethod($signature)
    {
        list($method,) = explode(':', $signature);
        return 'Am_Crypt_'.  ucfirst($method);
    }

    public
        function checkKeyChanged()
    {
        if ($this->compareKeySignatures() != 0)
            throw new Am_Exception_Crypt('The encryption key has been changed, '
            . 'you have to re-encode database with new key - please visit '
            . '<a href="' . Am_Di::getInstance()->url('cc/admin-convert') . '">upgrade script</a>');
    }

    /**
     * @return 0 if the same, 1 if different signatures
     */
    public
        function compareKeySignatures()
    {
        if ($this->loadKeySignature() == '')
        {
            $this->saveKeySigunature();
            return 0;
        }
        $loadedSig = $this->loadKeySignature();
        if (preg_match('/^strong:/', $loadedSig))
        {
            $ret = strcmp($loadedSig, $this->getKeySignatureCompat());
            if ($ret === 0)
                $this->saveKeySigunature();
            return $ret;
        } else
        {
            return strcmp($loadedSig, $this->getKeySignature());
        }
    }

    public
        function saveKeySigunature()
    {
        $sign = $this->getKeySignature($this->key);
        Am_Di::getInstance()->config->saveValue('crypt_key_signature', $sign);
        Am_Di::getInstance()->config->set('crypt_key_signature', $sign);
    }

    public
        function loadKeySignature()
    {
        return Am_Di::getInstance()->config->get('crypt_key_signature');
    }

}

/**
 * Old encryption method used in v3
 * @package Am_Crypt
 */
class Am_Crypt_Compat extends Am_Crypt
{

    const
        DEFAULT_KEY = 'Xjk23cbnmk28;ajandb4b300zxchB&!@^#$DOFCNCccc334ff,masd';

    function __construct($key = null)
    {
        if ($key === null)
            $key = self::DEFAULT_KEY;
        parent::__construct($key);
    }

    function encrypt($s)
    {
        return rawurlencode($this->__internal_crypt($s, $this->key));
    }

    function decrypt($s)
    {
        return rawurldecode(rawurlencode($this->__internal_crypt(rawurldecode($s), $this->key)));
    }

    function getKeySignature()
    {
        return self::getMethod() . ':'.crc32(substr($this->key, 0, 2) . sha1($this->key) . substr($this->key, -2, 2));
    }

    function __internal_crypt($data, $pwd)
    {
        $cb = '';
        settype($cb, 'array');
        settype($tt, 'string');
        $kk = '';
        settype($kk, 'array');
        for ($i = 0, $pl = strlen($pwd); $i < 256; $i++)
        {
            $kk[$i] = ord(substr($pwd, ($i % $pl), 1));
            $cb[$i] = $i;
        }
        for ($i = 0, $j = 0; $i < 256; $i++)
        {
            $j = ($j + $cb[$i] + $kk[$i]) % 256;
            $tt = $cb[$i];
            $cb[$i] = $cb[$j];
            $cb[$j] = $tt;
        }
        $tttt = $k = $news = $newss = '';
        $a = 0;
        $j = 0;
        for ($i = 0; $i < strlen($data); $i++)
        {
            $a += 1;
            $a %= 256;
            $j += $cb[$a];
            $j %= 256;
            $tttt = $cb[$a];
            $cb[$a] = $cb[$j];
            $cb[$j] = $tttt;
            $k = $cb[(($cb[$a] + $cb[$j]) % 256)];
            $newss .= chr(ord(substr($data, $i, 1)) ^ $k);
        }
        return $newss;
    }

}

/**
 * New encryption based on mcrypt 3des
 * @package Am_Crypt
 */
class Am_Crypt_Strong extends Am_Crypt
{

    protected
        $chKey,
        $key;
    const
        CIPHER = 'des-ede3';
    function __construct($key = null)
    {
        if (!function_exists('openssl_encrypt'))
            throw new Am_Exception_Crypt("openssl module is not enabled");
        if ($key === null)
            $key = $this->openKeyFile();
        $this->chKey = substr(pack("H*", md5($key)), 0, $this->getIVLength());
        parent::__construct($key);
    }

    protected
        function getIVLength()
    {
        return 24;
    }

    public
        function openKeyFile()
    {
        if (strpos(AM_KEYFILE, 'const:')===0)
        {
            return substr(AM_KEYFILE, strlen('const:'));
        }
        $path = AM_KEYFILE;
        if (!file_exists($path))
            throw new Am_Exception_Crypt_Key('Key file does not exists'); // @todo comment
        $key = include $path;
        if (!strlen($key))
            throw new Am_Exception_Crypt_Key('Key file has incorrect format or the key is empty'); // @todo comment
        if ($key == 'REPLACE THIS STRING TO YOUR KEYSTRING')
            throw new Am_Exception_Crypt_Key("You must define a valid key in the file [$path] instead of default");
        return $key;
    }

    function encrypt($s)
    {
        if ($s == '')
            return $s;
        return base64_encode(openssl_encrypt($s, self::CIPHER, $this->chKey, OPENSSL_ZERO_PADDING| OPENSSL_RAW_DATA, ''));
    }

    function decrypt($s)
    {
        if ($s == '')
            return $s;
        return trim(openssl_decrypt(base64_decode($s), self::CIPHER, $this->chKey, OPENSSL_ZERO_PADDING | OPENSSL_RAW_DATA, ''));
    }

    static function getMethod()
    {
        return 'strong2';
    }

    function getKeySignature()
    {
        return self::getMethod() . ':' . substr(sha1($this->key), 4, -4);
    }

    function getKeySignatureCompat()
    {
        return 'strong:' . crc32(substr($this->key, 0, 2) . sha1($this->key) . substr($this->key, -2, 2));
    }

}
class Am_Crypt_Strong2 extends Am_Crypt_Strong{}

class Am_Crypt_Aes128 extends Am_Crypt
{

    protected
        $chKey,
        $key;
    const
        CIPHER = 'AES-128-CBC';
    function __construct($key = null)
    {
        if (!function_exists('openssl_encrypt'))
            throw new Am_Exception_Crypt("openssl module is not enabled");
        if ($key === null)
            $key = $this->openKeyFile();
        $this->chKey = substr(pack("H*", md5($key)), 0, $this->getIVLength());
        $this->chKeyCompat = substr(pack("H*", md5(null)), 0, $this->getIVLength());

        parent::__construct($key);
    }

    protected
        function getIVLength()
    {
        return openssl_cipher_iv_length(self::CIPHER);
    }

    public
        function openKeyFile()
    {
        if (strpos(AM_KEYFILE, 'const:')===0)
        {
            return substr(AM_KEYFILE, strlen('const:'));
        }
        $path = AM_KEYFILE;
        if (!file_exists($path))
            throw new Am_Exception_Crypt_Key('Key file does not exists'); // @todo comment
        $key = include $path;
        if (!strlen($key))
            throw new Am_Exception_Crypt_Key('Key file has incorrect format or the key is empty'); // @todo comment
        if ($key == 'REPLACE THIS STRING TO YOUR KEYSTRING')
            throw new Am_Exception_Crypt_Key("You must define a valid key in the file [$path] instead of default");
        return $key;
    }

    function encrypt($s)
    {
        if ($s == '')
            return $s;
        $iv = openssl_random_pseudo_bytes($this->getIVLength());
        $enc = openssl_encrypt($s, self::CIPHER, $this->chKey, OPENSSL_RAW_DATA, $iv);
        return sprintf("%s:%s", self::getMethod(), base64_encode($iv . $enc));
    }

    function decrypt($s)
    {
        if ($s == '')
            return $s;
        
        $ivSize = $this->getIVLength();
        $compat = false;
        if(strpos($s, ":")!== false){
            $chKey = $this->chKey;
            list(,$s) = explode(":",$s);
        }else{
            $compat = true;
            $chKey = $this->chKeyCompat;
        }
        $s = base64_decode($s);
        $iv = substr($s, 0, $ivSize);
        $res = openssl_decrypt(substr($s, $ivSize), self::CIPHER, $chKey, OPENSSL_RAW_DATA, $iv);;
        if($compat && !$res)
        {
            $chKey = $this->chKey;
            $res = openssl_decrypt(substr($s, $ivSize), self::CIPHER, $chKey, OPENSSL_RAW_DATA, $iv);;
        }
        return $res;
    }

    function getKeySignature()
    {
        return self::getMethod() . ':' . substr(sha1($this->key), 4, -4);
    }

    function getKeySignatureCompat()
    {
        return 'strong2' . ':' . substr(sha1($this->key), 4, -4);
    }
}
