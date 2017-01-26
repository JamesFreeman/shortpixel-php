<?php

namespace ShortPixel;

class Source {
    private $urls;

    /**
     * @param $path - the file path on the local drive
     * @param $basePath - common base path used to determine the subfolders that will be created in the destination
     * @return Commander - the class that handles the optimization commands
     * @throws ClientException
     */
    public function fromFiles($paths, $basePath = null, $pending = null) {
        if(!is_array($paths)) {
            $paths = array($paths);
        }
        if(count($paths) > ShortPixel::MAX_ALLOWED_FILES_PER_CALL) {
            throw new ClientException("Maximum 10 local images allowed per call.");
        }
        $files = array();
        foreach($paths as $path) {
            if (!file_exists($path)) throw new ClientException("File not found: " . $path);
            $files[] = $path;
        }
        $data       = array(
            "plugin_version" => LIBRARY_CODE . " " . VERSION,
            "key" =>  ShortPixel::getKey(),
            "files" => $files
        );
        if($pending && count($pending)) {
            $data["pendingURLs"] = $pending;
        }

        return new Commander($data, $this);
    }

    /**
     * returns the optimization counters of the folder and subfolders
     * @param $path
     * @return (object)array('status', 'total', 'succeeded', 'pending', 'same', 'failed')
     * @throws PersistException
     */
    public function folderInfo($path){
        $persister = ShortPixel::getPersister($path);
        if(!$persister) {
            throw new PersistException("Persist is not enabled in options, needed for fetching folder info");
        }
        return $persister->info($path);
    }

    /**
     * processes a chunk of MAX_ALLOWED files from the folder, based on the persisted information about which images are processed and which not. This information is offered by the Persister object.
     * @param $path - the folder path on the local drive
     * @return Commander - the class that handles the optimization commands
     * @throws ClientException
     */
    public function fromFolder($path, $maxFiles = ShortPixel::MAX_ALLOWED_FILES_PER_CALL) {
        //sanitize
        $maxFiles = min(1, max(ShortPixel::MAX_ALLOWED_FILES_PER_CALL, intval($maxFiles)));

        $persister = ShortPixel::getPersister($path);
        if(!$persister) {
            throw new PersistException("Persist is not enabled in options, needed for folder optimization");
        }
        $paths = $persister->getTodo($path, $maxFiles);
        if($paths) {
            ShortPixel::setOptions(array("base_source_path" => $path));
            return $this->fromFiles($paths->files, null, $paths->filesPending);
        }
        throw new ClientException("Couldn't find any processable file at given path.");
    }

    /**
     * processes a chunk of MAX_ALLOWED URLs from a folder that is accessible via web at the $webPath location,
     * based on the persisted information about which images are processed and which not. This information is offered by the Persister object.
     * @param $path - the folder path on the local drive
     * @param $webPath - the web URL of the folder
     * @return Commander - the class that handles the optimization commands
     * @throws ClientException
     */
    public function fromWebFolder($path, $webPath) {
        $paths = ShortPixel::getPersister()->getTodo($path, ShortPixel::MAX_ALLOWED_FILES_PER_CALL);
        if(count($paths->files)) {
            $repl = (object)array("path" => $path, "web" => $webPath);
            $items = array_merge($paths->files, array_values($paths->filesPending)); //not impossible to have filesPending - for example optimized partially without webPath then added it
            array_walk(
                $items,
                function(&$item, $key, $repl){
                    $item = str_replace($repl->path, $repl->web, $item);
                }, $repl);
            ShortPixel::setOptions(array("base_url" => $webPath, "base_source_path" => $path));
            return $this->fromUrls($items);
        }
        throw new ClientException("Couldn't find any processable file at given path.");
    }

    public function fromBuffer($string) {
        throw new ClientException("fromBuffer not implemented");
    }

    /**
     * @param $urls - the array of urls to be optimized
     * @return Commander - the class that handles the optimization commands
     * @throws ClientException
     */
    public function fromUrls($urls) {
        if(!is_array($urls)) {
            $urls = array($urls);
        }
        if(count($urls) > 100) {
            throw new ClientException("Maximum 100 images allowed per call.");
        }

        $this->urls = array_map ('utf8_encode',  $urls);
        $data       = array(
            "plugin_version" => LIBRARY_CODE . " " . VERSION,
            "key" =>  ShortPixel::getKey(),
            "urllist" => $this->urls
        );

        return new Commander($data, $this);
    }
}
