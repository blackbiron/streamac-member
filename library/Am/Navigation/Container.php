<?php

class Am_Navigation_Container implements RecursiveIterator, Countable
{
    /**
     * Contains sub pages
     *
     * @var array
     */
    protected $_pages = [];

    /**
     * An index that contains the order in which to iterate pages
     *
     * @var array
     */
    protected $_index = [];

    /**
     * Whether index is dirty and needs to be re-arranged
     *
     * @var bool
     */
    protected $_dirtyIndex = false;

    // Internal methods:

    /**
     * Sorts the page index according to page order
     *
     * @return void
     */
    protected function _sort()
    {
        if ($this->_dirtyIndex) {
            $newIndex = [];
            $index = 0;

            foreach ($this->_pages as $hash => $page) {
                $order = $page->getOrder();
                if ($order === null) {
                    $newIndex[$hash] = $index;
                    $index++;
                } else {
                    $newIndex[$hash] = $order;
                }
            }

            asort($newIndex);
            $this->_index = $newIndex;
            $this->_dirtyIndex = false;
        }
    }

    // Public methods:

    /**
     * Notifies container that the order of pages are updated
     *
     * @return void
     */
    public function notifyOrderUpdated()
    {
        $this->_dirtyIndex = true;
    }

    /**
     * Adds a page to the container
     *
     * This method will inject the container as the given page's parent by
     * calling {@link Am_Navigation_Page::setParent()}.
     *
     * @param  Am_Navigation_Page|array|Am_Config $page  page to add
     * @return Am_Navigation_Container                     fluent interface,
     *                                                       returns self
     * @throws Am_Navigation_Exception                     if page is invalid
     */
    public function addPage($page, $returnPage = false)
    {
        if ($page === $this) {
            //--//require_once 'Zend/Navigation/Exception.php';
            throw new Am_Navigation_Exception(
                'A page cannot have itself as a parent');
        }

        if (is_array($page) || $page instanceof Am_Config) {
            //--//require_once 'Zend/Navigation/Page.php';
            $page = Am_Navigation_Page::factory($page);
        } elseif (!$page instanceof Am_Navigation_Page) {
            //--//require_once 'Zend/Navigation/Exception.php';
            throw new Am_Navigation_Exception(
                    'Invalid argument: $page must be an instance of ' .
                    'Am_Navigation_Page or Am_Config, or an array');
        }

        $hash = $page->hashCode();

        if (array_key_exists($hash, $this->_index)) {
            // page is already in container
            return $this;
        }

        if (!$page->getOrder()) {
            $page->setOrder(200);
        }

        // adds page to container and sets dirty flag
        $this->_pages[$hash] = $page;
        $this->_index[$hash] = $page->getOrder();
        $this->_dirtyIndex = true;

        // inject self as page parent
        $page->setParent($this);

        return $returnPage ? $page : $this;
    }

    /**
     * Adds several pages at once
     *
     * @param  array|Am_Config|Am_Navigation_Container  $pages  pages to add
     * @return Am_Navigation_Container                    fluent interface,
     *                                                      returns self
     * @throws Am_Navigation_Exception                    if $pages is not
     *                                                      array, Am_Config or
     *                                                      Am_Navigation_Container
     */
    public function addPages($pages)
    {
        if ($pages instanceof Am_Config) {
            $pages = $pages->toArray();
        }

        if ($pages instanceof Am_Navigation_Container) {
            $pages = iterator_to_array($pages);
        }

        if (!is_array($pages)) {
            //--//require_once 'Zend/Navigation/Exception.php';
            throw new Am_Navigation_Exception(
                    'Invalid argument: $pages must be an array, an ' .
                    'instance of Am_Config or an instance of ' .
                    'Am_Navigation_Container');
        }

        foreach ($pages as $page) {
            $this->addPage($page);
        }

        return $this;
    }

    /**
     * Sets pages this container should have, removing existing pages
     *
     * @param  array $pages               pages to set
     * @return Am_Navigation_Container  fluent interface, returns self
     */
    public function setPages(array $pages)
    {
        $this->removePages();
        return $this->addPages($pages);
    }

    /**
     * Returns pages in the container
     *
     * @return array  array of Am_Navigation_Page instances
     */
    public function getPages()
    {
        return $this->_pages;
    }

    /**
     * Removes the given page from the container
     *
     * @param  Am_Navigation_Page|int $page  page to remove, either a page
     *                                         instance or a specific page order
     * @return bool                            whether the removal was
     *                                         successful
     */
    public function removePage($page)
    {
        if ($page instanceof Am_Navigation_Page) {
            $hash = $page->hashCode();
        } elseif (is_int($page)) {
            $this->_sort();
            if (!$hash = array_search($page, $this->_index)) {
                return false;
            }
        } else {
            return false;
        }

        if (isset($this->_pages[$hash])) {
            unset($this->_pages[$hash]);
            unset($this->_index[$hash]);
            $this->_dirtyIndex = true;
            return true;
        }

        return false;
    }

    /**
     * Removes all pages in container
     *
     * @return Am_Navigation_Container  fluent interface, returns self
     */
    public function removePages()
    {
        $this->_pages = [];
        $this->_index = [];
        return $this;
    }

    /**
     * Checks if the container has the given page
     *
     * @param  Am_Navigation_Page $page       page to look for
     * @param  bool                 $recursive  [optional] whether to search
     *                                          recursively. Default is false.
     * @return bool                             whether page is in container
     */
    public function hasPage(Am_Navigation_Page $page, $recursive = false)
    {
        if (array_key_exists($page->hashCode(), $this->_index)) {
            return true;
        } elseif ($recursive) {
            foreach ($this->_pages as $childPage) {
                if ($childPage->hasPage($page, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns true if container contains any pages
     *
     * @return bool  whether container has any pages
     */
    public function hasPages()
    {
        return count($this->_index) > 0;
    }

    /**
     * Returns a child page matching $property == $value or
     * preg_match($value, $property), or null if not found
     *
     * @param  string  $property          name of property to match against
     * @param  mixed   $value             value to match property against
     * @param  bool    $useRegex          [optional] if true PHP's preg_match
     *                                    is used. Default is false.
     * @return Am_Navigation_Page|null  matching page or null
     */
    public function findOneBy($property, $value, $useRegex = false)
    {
        $iterator = new RecursiveIteratorIterator(
            $this,
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $page) {
            $pageProperty = $page->get($property);

            // Rel and rev
            if (is_array($pageProperty)) {
                foreach ($pageProperty as $item) {
                    if (is_array($item)) {
                        // Use regex?
                        if (true === $useRegex) {
                            foreach ($item as $item2) {
                                if (0 !== preg_match($value, $item2)) {
                                    return $page;
                                }
                            }
                        } else {
                            if (in_array($value, $item)) {
                                return $page;
                            }
                        }
                    } else {
                        // Use regex?
                        if (true === $useRegex) {
                            if (0 !== preg_match($value, $item)) {
                                return $page;
                            }
                        } else {
                            if ($item == $value) {
                                return $page;
                            }
                        }
                    }
                }

                continue;
            }

            // Use regex?
            if (true === $useRegex) {
                if (preg_match($value, $pageProperty)) {
                    return $page;
                }
            } else {
                if ($pageProperty == $value) {
                    return $page;
                }
            }
        }

        return null;
    }

    /**
     * Returns all child pages matching $property == $value or
     * preg_match($value, $property), or an empty array if no pages are found
     *
     * @param  string $property  name of property to match against
     * @param  mixed  $value     value to match property against
     * @param  bool   $useRegex  [optional] if true PHP's preg_match is used.
     *                           Default is false.
     * @return array             array containing only Am_Navigation_Page
     *                           instances
     */
    public function findAllBy($property, $value, $useRegex = false)
    {
        $found = [];

        $iterator = new RecursiveIteratorIterator(
            $this,
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $page) {
            $pageProperty = $page->get($property);

            // Rel and rev
            if (is_array($pageProperty)) {
                foreach ($pageProperty as $item) {
                    if (is_array($item)) {
                        // Use regex?
                        if (true === $useRegex) {
                            foreach ($item as $item2) {
                                if (0 !== preg_match($value, $item2)) {
                                    $found[] = $page;
                                }
                            }
                        } else {
                            if (in_array($value, $item)) {
                                $found[] = $page;
                            }
                        }
                    } else {
                        // Use regex?
                        if (true === $useRegex) {
                            if (0 !== preg_match($value, $item)) {
                                $found[] = $page;
                            }
                        } else {
                            if ($item == $value) {
                                $found[] = $page;
                            }
                        }
                    }
                }

                continue;
            }

            // Use regex?
            if (true === $useRegex) {
                if (0 !== preg_match($value, $pageProperty)) {
                    $found[] = $page;
                }
            } else {
                if ($pageProperty == $value) {
                    $found[] = $page;
                }
            }
        }

        return $found;
    }

    /**
     * Returns page(s) matching $property == $value or
     * preg_match($value, $property)
     *
     * @param  string $property  name of property to match against
     * @param  mixed  $value     value to match property against
     * @param  bool   $all       [optional] whether an array of all matching
     *                           pages should be returned, or only the first.
     *                           If true, an array will be returned, even if not
     *                           matching pages are found. If false, null will
     *                           be returned if no matching page is found.
     *                           Default is false.
     * @param  bool   $useRegex  [optional] if true PHP's preg_match is used.
     *                           Default is false.
     * @return Am_Navigation_Page|null  matching page or null
     */
    public function findBy($property, $value, $all = false, $useRegex = false)
    {
        if ($all) {
            return $this->findAllBy($property, $value, $useRegex);
        } else {
            return $this->findOneBy($property, $value, $useRegex);
        }
    }

    /**
     * Magic overload: Proxy calls to finder methods
     *
     * Examples of finder calls:
     * <code>
     * // METHOD                         // SAME AS
     * $nav->findByLabel('foo');         // $nav->findOneBy('label', 'foo');
     * $nav->findByLabel('/foo/', true); // $nav->findBy('label', '/foo/', true);
     * $nav->findOneByLabel('foo');      // $nav->findOneBy('label', 'foo');
     * $nav->findAllByClass('foo');      // $nav->findAllBy('class', 'foo');
     * </code>
     *
     * @param  string $method                       method name
     * @param  array  $arguments                    method arguments
     * @return mixed  Am_Navigation_Container|array|null    matching page, array of pages
     *                                              or null
     * @throws Am_Navigation_Exception            if method does not exist
     */
    public function __call($method, $arguments)
    {
        if (@preg_match('/(find(?:One|All)?By)(.+)/', $method, $match)) {
            return $this->{$match[1]}($match[2], $arguments[0], !empty($arguments[1]));
        }

        //--//require_once 'Zend/Navigation/Exception.php';
        throw new Am_Navigation_Exception(
            sprintf(
                'Bad method call: Unknown method %s::%s',
                get_class($this),
                $method
            )
        );
    }

    /**
     * Returns an array representation of all pages in container
     *
     * @return array
     */
    public function toArray()
    {
        $pages = [];

        $this->_dirtyIndex = true;
        $this->_sort();
        $indexes = array_keys($this->_index);
        foreach ($indexes as $hash) {
            $pages[] = $this->_pages[$hash]->toArray();
        }
        return $pages;
    }

    // RecursiveIterator interface:

    /**
     * Returns current page
     *
     * Implements RecursiveIterator interface.
     *
     * @return Am_Navigation_Page       current page or null
     * @throws Am_Navigation_Exception  if the index is invalid
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        $this->_sort();
        current($this->_index);
        $hash = key($this->_index);

        if (isset($this->_pages[$hash])) {
            return $this->_pages[$hash];
        } else {
            //--//require_once 'Zend/Navigation/Exception.php';
            throw new Am_Navigation_Exception(
                    'Corruption detected in container; ' .
                    'invalid key found in internal iterator');
        }
    }

    /**
     * Returns hash code of current page
     *
     * Implements RecursiveIterator interface.
     *
     * @return string  hash code of current page
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        $this->_sort();
        return key($this->_index);
    }

    /**
     * Moves index pointer to next page in the container
     *
     * Implements RecursiveIterator interface.
     *
     * @return void
     */
    public function next(): void
    {
        $this->_sort();
        next($this->_index);
    }

    /**
     * Sets index pointer to first page in the container
     *
     * Implements RecursiveIterator interface.
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->_sort();
        reset($this->_index);
    }

    /**
     * Checks if container index is valid
     *
     * Implements RecursiveIterator interface.
     *
     * @return bool
     */
    public function valid(): bool
    {
        $this->_sort();
        return current($this->_index) !== false;
    }

    /**
     * Proxy to hasPages()
     *
     * Implements RecursiveIterator interface.
     *
     * @return bool  whether container has any pages
     */
    public function hasChildren(): bool
    {
        return $this->hasPages();
    }

    /**
     * Returns the child container.
     *
     * Implements RecursiveIterator interface.
     *
     * @return Am_Navigation_Page|null
     */
    public function getChildren(): ?RecursiveIterator
    {
        $hash = key($this->_index);

        if (isset($this->_pages[$hash])) {
            return $this->_pages[$hash];
        }

        return null;
    }

    // Countable interface:

    /**
     * Returns number of pages in container
     *
     * Implements Countable interface.
     *
     * @return int  number of pages in the container
     */
    public function count(): int
    {
        return count($this->_index);
    }
}