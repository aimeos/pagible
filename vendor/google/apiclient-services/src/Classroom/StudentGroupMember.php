<?php
/*
 * Copyright 2014 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not
 * use this file except in compliance with the License. You may obtain a copy of
 * the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations under
 * the License.
 */

namespace Google\Service\Classroom;

class StudentGroupMember extends \Google\Model
{
  /**
   * The identifier of the course.
   *
   * @var string
   */
  public $courseId;
  /**
   * The identifier of the student group.
   *
   * @var string
   */
  public $studentGroupId;
  /**
   * Identifier of the student.
   *
   * @var string
   */
  public $userId;

  /**
   * The identifier of the course.
   *
   * @param string $courseId
   */
  public function setCourseId($courseId)
  {
    $this->courseId = $courseId;
  }
  /**
   * @return string
   */
  public function getCourseId()
  {
    return $this->courseId;
  }
  /**
   * The identifier of the student group.
   *
   * @param string $studentGroupId
   */
  public function setStudentGroupId($studentGroupId)
  {
    $this->studentGroupId = $studentGroupId;
  }
  /**
   * @return string
   */
  public function getStudentGroupId()
  {
    return $this->studentGroupId;
  }
  /**
   * Identifier of the student.
   *
   * @param string $userId
   */
  public function setUserId($userId)
  {
    $this->userId = $userId;
  }
  /**
   * @return string
   */
  public function getUserId()
  {
    return $this->userId;
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(StudentGroupMember::class, 'Google_Service_Classroom_StudentGroupMember');
