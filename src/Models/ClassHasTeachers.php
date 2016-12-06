<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace Webuntis\Models;


use Webuntis\Query\Query;

/**
 * Class ClassHasTeachers
 * @package Webuntis\Models
 * @author Tobias Franek <tobias.franek@gmail.com>
 */
class ClassHasTeachers extends AbstractModel {
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $fullName;
    /**
     * @var array
     */
    private $teachers = [];

    /**
     * parses the given data from the json rpc api to the right format for the object
     * @param array $data
     */
    public function parse($data) {
        $this->setId($data['id']);
        $this->name = $data['name'];
        $this->fullName = $data['longName'];
        $query = new Query();
        foreach ($data['teachers'] as $value) {
            $this->teachers[] = $query->get('Teachers')->findBy(['id' => $value['id']])[0];
        }
    }

    /**
     * serializes the object and returns an array with the objects values
     * @return array
     */
    public function serialize() {
        return [
            'id' => $this->getId(),
            'name' => $this->name,
            'fullName' => $this->fullName,
            'teachers' => $this->getSerializedTeachers()
        ];
    }

    /**
     * serializes the teachers
     * @return array
     */
    private function getSerializedTeachers() {
        $result = [];
        foreach ($this->teachers as $value) {
            $result[] = $value->serialize();
        }
        return $result;
    }

    /**
     * returns the Teachers
     * @return array
     */
    public function getTeachers() {
        return $this->teachers;
    }

    /**
     * sets the Teachers
     * @param Teachers[] $teachers
     * @return $this
     */
    public function setTeachers(array $teachers) {
        $this->teachers = $teachers;

        return $this;
    }
    /**
     * returns the name
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * set the name
     * @param string $name
     * @return Classes $this
     */
    public function setName($name) {
        $this->name = $name;

        return $this;
    }

    /**
     * returns the fullName
     * @return string
     */
    public function getFullName() {
        return $this->fullName;
    }

    /**
     * set the fullName
     * @param string $fullName
     * @return Classes $this
     */
    public function setFullName($fullName) {
        $this->fullName = $fullName;

        return $this;
    }
}