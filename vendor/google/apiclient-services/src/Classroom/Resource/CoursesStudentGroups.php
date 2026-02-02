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

namespace Google\Service\Classroom\Resource;

use Google\Service\Classroom\ClassroomEmpty;
use Google\Service\Classroom\ListStudentGroupsResponse;
use Google\Service\Classroom\StudentGroup;

/**
 * The "studentGroups" collection of methods.
 * Typical usage is:
 *  <code>
 *   $classroomService = new Google\Service\Classroom(...);
 *   $studentGroups = $classroomService->courses_studentGroups;
 *  </code>
 */
class CoursesStudentGroups extends \Google\Service\Resource
{
  /**
   * Creates a student group for a course. This method returns the following error
   * codes: * `PERMISSION_DENIED` if the requesting user is not permitted to
   * create the student group or for access errors. * `NOT_FOUND` if the course
   * does not exist or the requesting user doesn't have access to the course. *
   * `FAILED_PRECONDITION` if creating the student group would exceed the maximum
   * number of student groups per course. (studentGroups.create)
   *
   * @param string $courseId Required. The identifier of the course.
   * @param StudentGroup $postBody
   * @param array $optParams Optional parameters.
   * @return StudentGroup
   * @throws \Google\Service\Exception
   */
  public function create($courseId, StudentGroup $postBody, $optParams = [])
  {
    $params = ['courseId' => $courseId, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('create', [$params], StudentGroup::class);
  }
  /**
   * Deletes a student group. This method returns the following error codes: *
   * `PERMISSION_DENIED` if the requesting user is not permitted to delete the
   * requested student group or for access errors. * `NOT_FOUND` if the student
   * group does not exist or the user does not have access to the student group.
   * (studentGroups.delete)
   *
   * @param string $courseId Required. The identifier of the course containing the
   * student group to delete.
   * @param string $id Required. The identifier of the student group to delete.
   * @param array $optParams Optional parameters.
   * @return ClassroomEmpty
   * @throws \Google\Service\Exception
   */
  public function delete($courseId, $id, $optParams = [])
  {
    $params = ['courseId' => $courseId, 'id' => $id];
    $params = array_merge($params, $optParams);
    return $this->call('delete', [$params], ClassroomEmpty::class);
  }
  /**
   * Returns a list of groups in a course. This method returns the following error
   * codes: * `NOT_FOUND` if the course does not exist.
   * (studentGroups.listCoursesStudentGroups)
   *
   * @param string $courseId Required. The identifier of the course.
   * @param array $optParams Optional parameters.
   *
   * @opt_param int pageSize Maximum number of items to return. Zero or
   * unspecified indicates that the server may assign a maximum, which is
   * currently set to 75 items. The server may return fewer than the specified
   * number of results.
   * @opt_param string pageToken nextPageToken value returned from a previous list
   * call, indicating that the subsequent page of results should be returned. The
   * list request must be otherwise identical to the one that resulted in this
   * token.
   * @return ListStudentGroupsResponse
   * @throws \Google\Service\Exception
   */
  public function listCoursesStudentGroups($courseId, $optParams = [])
  {
    $params = ['courseId' => $courseId];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], ListStudentGroupsResponse::class);
  }
  /**
   * Updates one or more fields in a student group. This method returns the
   * following error codes: * `PERMISSION_DENIED` if the requesting user is not
   * permitted to modify the requested student group or for access errors. *
   * `NOT_FOUND` if the student group does not exist or the user does not have
   * access to the student group. * `INVALID_ARGUMENT` if invalid fields are
   * specified in the update mask or if no update mask is supplied.
   * (studentGroups.patch)
   *
   * @param string $courseId Required. Identifier of the course.
   * @param string $id Required. Identifier of the student group.
   * @param StudentGroup $postBody
   * @param array $optParams Optional parameters.
   *
   * @opt_param string updateMask Required. Mask that identifies which fields on
   * the student group to update. This field is required to do an update. The
   * update fails if invalid fields are specified. The following fields can be
   * specified by teachers: * `title`
   * @return StudentGroup
   * @throws \Google\Service\Exception
   */
  public function patch($courseId, $id, StudentGroup $postBody, $optParams = [])
  {
    $params = ['courseId' => $courseId, 'id' => $id, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('patch', [$params], StudentGroup::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(CoursesStudentGroups::class, 'Google_Service_Classroom_Resource_CoursesStudentGroups');
