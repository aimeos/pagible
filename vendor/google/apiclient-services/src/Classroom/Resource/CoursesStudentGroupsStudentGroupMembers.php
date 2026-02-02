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
use Google\Service\Classroom\ListStudentGroupMembersResponse;
use Google\Service\Classroom\StudentGroupMember;

/**
 * The "studentGroupMembers" collection of methods.
 * Typical usage is:
 *  <code>
 *   $classroomService = new Google\Service\Classroom(...);
 *   $studentGroupMembers = $classroomService->courses_studentGroups_studentGroupMembers;
 *  </code>
 */
class CoursesStudentGroupsStudentGroupMembers extends \Google\Service\Resource
{
  /**
   * Creates a student group member for a student group. This method returns the
   * following error codes: * `PERMISSION_DENIED` if the requesting user is not
   * permitted to create the student group or member for access errors. *
   * `NOT_FOUND` if the student group does not exist or the user does not have
   * access to the student group. * `ALREADY_EXISTS` if the student group member
   * already exists. * `FAILED_PRECONDITION` if attempting to add a member to a
   * student group that has reached its member limit. (studentGroupMembers.create)
   *
   * @param string $courseId Required. The identifier of the course.
   * @param string $studentGroupId Required. The identifier of the student group.
   * @param StudentGroupMember $postBody
   * @param array $optParams Optional parameters.
   * @return StudentGroupMember
   * @throws \Google\Service\Exception
   */
  public function create($courseId, $studentGroupId, StudentGroupMember $postBody, $optParams = [])
  {
    $params = ['courseId' => $courseId, 'studentGroupId' => $studentGroupId, 'postBody' => $postBody];
    $params = array_merge($params, $optParams);
    return $this->call('create', [$params], StudentGroupMember::class);
  }
  /**
   * Deletes a student group member. This method returns the following error
   * codes: * `PERMISSION_DENIED` if the requesting user is not permitted to
   * delete the requested student group member or for access errors. * `NOT_FOUND`
   * if the student group member does not exist or the user does not have access
   * to the student group. (studentGroupMembers.delete)
   *
   * @param string $courseId Required. The identifier of the course containing the
   * relevant student group.
   * @param string $studentGroupId Required. The identifier of the student group
   * containing the student group member to delete.
   * @param string $userId Required. The identifier of the student group member to
   * delete.
   * @param array $optParams Optional parameters.
   * @return ClassroomEmpty
   * @throws \Google\Service\Exception
   */
  public function delete($courseId, $studentGroupId, $userId, $optParams = [])
  {
    $params = ['courseId' => $courseId, 'studentGroupId' => $studentGroupId, 'userId' => $userId];
    $params = array_merge($params, $optParams);
    return $this->call('delete', [$params], ClassroomEmpty::class);
  }
  /**
   * Returns a list of students in a group. This method returns the following
   * error codes: * `NOT_FOUND` if the course or student group does not exist.
   * (studentGroupMembers.listCoursesStudentGroupsStudentGroupMembers)
   *
   * @param string $courseId Required. The identifier of the course.
   * @param string $studentGroupId Required. The identifier of the student group.
   * @param array $optParams Optional parameters.
   *
   * @opt_param int pageSize Maximum number of items to return. Zero or
   * unspecified indicates that the server may assign a maximum. The server may
   * return fewer than the specified number of results.
   * @opt_param string pageToken nextPageToken value returned from a previous list
   * call, indicating that the subsequent page of results should be returned. The
   * list request must be otherwise identical to the one that resulted in this
   * token.
   * @return ListStudentGroupMembersResponse
   * @throws \Google\Service\Exception
   */
  public function listCoursesStudentGroupsStudentGroupMembers($courseId, $studentGroupId, $optParams = [])
  {
    $params = ['courseId' => $courseId, 'studentGroupId' => $studentGroupId];
    $params = array_merge($params, $optParams);
    return $this->call('list', [$params], ListStudentGroupMembersResponse::class);
  }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(CoursesStudentGroupsStudentGroupMembers::class, 'Google_Service_Classroom_Resource_CoursesStudentGroupsStudentGroupMembers');
