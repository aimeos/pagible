<?php return array (
  'types' => 
  array (
    'DateTime' => 
    array (
      'kind' => 'ScalarTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'DateTime',
      ),
      'directives' => 
      array (
        0 => 
        array (
          'kind' => 'Directive',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'scalar',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'Argument',
              'value' => 
              array (
                'kind' => 'StringValue',
                'value' => 'Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\DateTime',
                'block' => false,
              ),
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'class',
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'A datetime string with format `Y-m-d H:i:s`, e.g. `2018-05-23 13:43:32`.',
        'block' => false,
      ),
    ),
    'Query' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Query',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'user',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'ID',
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'eq',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'rules',
                  ),
                  'arguments' => 
                  array (
                    0 => 
                    array (
                      'kind' => 'Argument',
                      'value' => 
                      array (
                        'kind' => 'ListValue',
                        'values' => 
                        array (
                          0 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'prohibits:email',
                            'block' => false,
                          ),
                          1 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'required_without:email',
                            'block' => false,
                          ),
                        ),
                      ),
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'apply',
                      ),
                    ),
                  ),
                ),
              ),
              'description' => 
              array (
                'kind' => 'StringValue',
                'value' => 'Search by primary key.',
                'block' => false,
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'email',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'eq',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'rules',
                  ),
                  'arguments' => 
                  array (
                    0 => 
                    array (
                      'kind' => 'Argument',
                      'value' => 
                      array (
                        'kind' => 'ListValue',
                        'values' => 
                        array (
                          0 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'prohibits:id',
                            'block' => false,
                          ),
                          1 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'required_without:id',
                            'block' => false,
                          ),
                          2 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'email',
                            'block' => false,
                          ),
                        ),
                      ),
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'apply',
                      ),
                    ),
                  ),
                ),
              ),
              'description' => 
              array (
                'kind' => 'StringValue',
                'value' => 'Search by email address.',
                'block' => false,
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'User',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'find',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Find a single user by an identifying attribute.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'me',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'User',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'auth',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Current authenticated user',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'page',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'eq',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 89,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 64,
                  'end' => 71,
                ),
                'kind' => 'Name',
                'value' => 'trashed',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 73,
                  'end' => 80,
                ),
                'kind' => 'NamedType',
                'name' => 
                array (
                  'loc' => 
                  array (
                    'start' => 73,
                    'end' => 80,
                  ),
                  'kind' => 'Name',
                  'value' => 'Trashed',
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 81,
                    'end' => 89,
                  ),
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 82,
                      'end' => 89,
                    ),
                    'kind' => 'Name',
                    'value' => 'trashed',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 63,
                ),
                'kind' => 'StringValue',
                'value' => 'Allows to filter if trashed elements should be fetched.',
                'block' => true,
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Page',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'view',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\Page',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'softDeletes',
              ),
              'arguments' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'find',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Get page by ID',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'element',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'eq',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 89,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 64,
                  'end' => 71,
                ),
                'kind' => 'Name',
                'value' => 'trashed',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 73,
                  'end' => 80,
                ),
                'kind' => 'NamedType',
                'name' => 
                array (
                  'loc' => 
                  array (
                    'start' => 73,
                    'end' => 80,
                  ),
                  'kind' => 'Name',
                  'value' => 'Trashed',
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 81,
                    'end' => 89,
                  ),
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 82,
                      'end' => 89,
                    ),
                    'kind' => 'Name',
                    'value' => 'trashed',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 63,
                ),
                'kind' => 'StringValue',
                'value' => 'Allows to filter if trashed elements should be fetched.',
                'block' => true,
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Element',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'view',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\Element',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'softDeletes',
              ),
              'arguments' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'find',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Get element item by ID',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'file',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'eq',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 89,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 64,
                  'end' => 71,
                ),
                'kind' => 'Name',
                'value' => 'trashed',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 73,
                  'end' => 80,
                ),
                'kind' => 'NamedType',
                'name' => 
                array (
                  'loc' => 
                  array (
                    'start' => 73,
                    'end' => 80,
                  ),
                  'kind' => 'Name',
                  'value' => 'Trashed',
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 81,
                    'end' => 89,
                  ),
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 82,
                      'end' => 89,
                    ),
                    'kind' => 'Name',
                    'value' => 'trashed',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 63,
                ),
                'kind' => 'StringValue',
                'value' => 'Allows to filter if trashed elements should be fetched.',
                'block' => true,
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'File',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'view',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\File',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'softDeletes',
              ),
              'arguments' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'find',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Get file item by ID',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'users',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'name',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'where',
                  ),
                  'arguments' => 
                  array (
                    0 => 
                    array (
                      'kind' => 'Argument',
                      'value' => 
                      array (
                        'kind' => 'StringValue',
                        'value' => 'like',
                        'block' => false,
                      ),
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'operator',
                      ),
                    ),
                  ),
                ),
              ),
              'description' => 
              array (
                'kind' => 'StringValue',
                'value' => 'Filters by name. Accepts SQL LIKE wildcards `%` and `_`.',
                'block' => false,
              ),
            ),
            1 => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 51,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 34,
                  'end' => 39,
                ),
                'kind' => 'Name',
                'value' => 'first',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 41,
                  'end' => 45,
                ),
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'loc' => 
                  array (
                    'start' => 41,
                    'end' => 44,
                  ),
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 41,
                      'end' => 44,
                    ),
                    'kind' => 'Name',
                    'value' => 'Int',
                  ),
                ),
              ),
              'defaultValue' => 
              array (
                'loc' => 
                array (
                  'start' => 49,
                  'end' => 51,
                ),
                'kind' => 'IntValue',
                'value' => '10',
              ),
              'directives' => 
              array (
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 33,
                ),
                'kind' => 'StringValue',
                'value' => 'Limits number of fetched items.',
                'block' => false,
              ),
            ),
            2 => 
            array (
              'loc' => 
              array (
                'start' => 4,
                'end' => 61,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 52,
                  'end' => 56,
                ),
                'kind' => 'Name',
                'value' => 'page',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 58,
                  'end' => 61,
                ),
                'kind' => 'NamedType',
                'name' => 
                array (
                  'loc' => 
                  array (
                    'start' => 58,
                    'end' => 61,
                  ),
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'directives' => 
              array (
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 4,
                  'end' => 47,
                ),
                'kind' => 'StringValue',
                'value' => 'The offset from which items are returned.',
                'block' => false,
              ),
            ),
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 0,
              'end' => 14,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 13,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 13,
                ),
                'kind' => 'Name',
                'value' => 'UserPaginator',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'paginate',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'IntValue',
                    'value' => '10',
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'defaultCount',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List multiple users.',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'pages',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'filter',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'PageFilter',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'publish',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Publish',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 52,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 34,
                  'end' => 39,
                ),
                'kind' => 'Name',
                'value' => 'first',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 41,
                  'end' => 45,
                ),
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'loc' => 
                  array (
                    'start' => 41,
                    'end' => 44,
                  ),
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 41,
                      'end' => 44,
                    ),
                    'kind' => 'Name',
                    'value' => 'Int',
                  ),
                ),
              ),
              'defaultValue' => 
              array (
                'loc' => 
                array (
                  'start' => 49,
                  'end' => 52,
                ),
                'kind' => 'IntValue',
                'value' => '100',
              ),
              'directives' => 
              array (
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 33,
                ),
                'kind' => 'StringValue',
                'value' => 'Limits number of fetched items.',
                'block' => false,
              ),
            ),
            3 => 
            array (
              'loc' => 
              array (
                'start' => 4,
                'end' => 61,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 52,
                  'end' => 56,
                ),
                'kind' => 'Name',
                'value' => 'page',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 58,
                  'end' => 61,
                ),
                'kind' => 'NamedType',
                'name' => 
                array (
                  'loc' => 
                  array (
                    'start' => 58,
                    'end' => 61,
                  ),
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'directives' => 
              array (
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 4,
                  'end' => 47,
                ),
                'kind' => 'StringValue',
                'value' => 'The offset from which items are returned.',
                'block' => false,
              ),
            ),
            4 => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 89,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 64,
                  'end' => 71,
                ),
                'kind' => 'Name',
                'value' => 'trashed',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 73,
                  'end' => 80,
                ),
                'kind' => 'NamedType',
                'name' => 
                array (
                  'loc' => 
                  array (
                    'start' => 73,
                    'end' => 80,
                  ),
                  'kind' => 'Name',
                  'value' => 'Trashed',
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 81,
                    'end' => 89,
                  ),
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 82,
                      'end' => 89,
                    ),
                    'kind' => 'Name',
                    'value' => 'trashed',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 63,
                ),
                'kind' => 'StringValue',
                'value' => 'Allows to filter if trashed elements should be fetched.',
                'block' => true,
              ),
            ),
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 0,
              'end' => 14,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 13,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 13,
                ),
                'kind' => 'Name',
                'value' => 'PagePaginator',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'paginate',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\GraphQL\\Query@pages',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'builder',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'IntValue',
                    'value' => '100',
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'defaultCount',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'view',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\Page',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'orderBy',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '_lft',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'column',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'EnumValue',
                    'value' => 'ASC',
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'direction',
                  ),
                ),
              ),
            ),
            3 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'softDeletes',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Get the available pages',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'elements',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'filter',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'ElementFilter',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'sort',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 33,
                ),
                'kind' => 'ListType',
                'type' => 
                array (
                  'loc' => 
                  array (
                    'start' => 1,
                    'end' => 32,
                  ),
                  'kind' => 'NonNullType',
                  'type' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 1,
                      'end' => 31,
                    ),
                    'kind' => 'NamedType',
                    'name' => 
                    array (
                      'loc' => 
                      array (
                        'start' => 1,
                        'end' => 31,
                      ),
                      'kind' => 'Name',
                      'value' => 'QueryElementsSortOrderByClause',
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'orderBy',
                  ),
                  'arguments' => 
                  array (
                    0 => 
                    array (
                      'kind' => 'Argument',
                      'value' => 
                      array (
                        'kind' => 'ListValue',
                        'values' => 
                        array (
                          0 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'id',
                            'block' => false,
                          ),
                          1 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'lang',
                            'block' => false,
                          ),
                          2 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'name',
                            'block' => false,
                          ),
                          3 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'type',
                            'block' => false,
                          ),
                          4 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'editor',
                            'block' => false,
                          ),
                        ),
                      ),
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'columns',
                      ),
                    ),
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'publish',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Publish',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            3 => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 52,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 34,
                  'end' => 39,
                ),
                'kind' => 'Name',
                'value' => 'first',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 41,
                  'end' => 45,
                ),
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'loc' => 
                  array (
                    'start' => 41,
                    'end' => 44,
                  ),
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 41,
                      'end' => 44,
                    ),
                    'kind' => 'Name',
                    'value' => 'Int',
                  ),
                ),
              ),
              'defaultValue' => 
              array (
                'loc' => 
                array (
                  'start' => 49,
                  'end' => 52,
                ),
                'kind' => 'IntValue',
                'value' => '100',
              ),
              'directives' => 
              array (
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 33,
                ),
                'kind' => 'StringValue',
                'value' => 'Limits number of fetched items.',
                'block' => false,
              ),
            ),
            4 => 
            array (
              'loc' => 
              array (
                'start' => 4,
                'end' => 61,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 52,
                  'end' => 56,
                ),
                'kind' => 'Name',
                'value' => 'page',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 58,
                  'end' => 61,
                ),
                'kind' => 'NamedType',
                'name' => 
                array (
                  'loc' => 
                  array (
                    'start' => 58,
                    'end' => 61,
                  ),
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'directives' => 
              array (
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 4,
                  'end' => 47,
                ),
                'kind' => 'StringValue',
                'value' => 'The offset from which items are returned.',
                'block' => false,
              ),
            ),
            5 => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 89,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 64,
                  'end' => 71,
                ),
                'kind' => 'Name',
                'value' => 'trashed',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 73,
                  'end' => 80,
                ),
                'kind' => 'NamedType',
                'name' => 
                array (
                  'loc' => 
                  array (
                    'start' => 73,
                    'end' => 80,
                  ),
                  'kind' => 'Name',
                  'value' => 'Trashed',
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 81,
                    'end' => 89,
                  ),
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 82,
                      'end' => 89,
                    ),
                    'kind' => 'Name',
                    'value' => 'trashed',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 63,
                ),
                'kind' => 'StringValue',
                'value' => 'Allows to filter if trashed elements should be fetched.',
                'block' => true,
              ),
            ),
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 0,
              'end' => 17,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 16,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 16,
                ),
                'kind' => 'Name',
                'value' => 'ElementPaginator',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'paginate',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\GraphQL\\Query@elements',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'builder',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'IntValue',
                    'value' => '100',
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'defaultCount',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'view',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\Element',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'orderBy',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'id',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'column',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'EnumValue',
                    'value' => 'DESC',
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'direction',
                  ),
                ),
              ),
            ),
            3 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'softDeletes',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Get available element items',
            'block' => false,
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'files',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'filter',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'FileFilter',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'sort',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 30,
                ),
                'kind' => 'ListType',
                'type' => 
                array (
                  'loc' => 
                  array (
                    'start' => 1,
                    'end' => 29,
                  ),
                  'kind' => 'NonNullType',
                  'type' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 1,
                      'end' => 28,
                    ),
                    'kind' => 'NamedType',
                    'name' => 
                    array (
                      'loc' => 
                      array (
                        'start' => 1,
                        'end' => 28,
                      ),
                      'kind' => 'Name',
                      'value' => 'QueryFilesSortOrderByClause',
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'orderBy',
                  ),
                  'arguments' => 
                  array (
                    0 => 
                    array (
                      'kind' => 'Argument',
                      'value' => 
                      array (
                        'kind' => 'ListValue',
                        'values' => 
                        array (
                          0 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'id',
                            'block' => false,
                          ),
                          1 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'name',
                            'block' => false,
                          ),
                          2 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'mime',
                            'block' => false,
                          ),
                          3 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'lang',
                            'block' => false,
                          ),
                          4 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'editor',
                            'block' => false,
                          ),
                          5 => 
                          array (
                            'kind' => 'StringValue',
                            'value' => 'byversions_count',
                            'block' => false,
                          ),
                        ),
                      ),
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'columns',
                      ),
                    ),
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'publish',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Publish',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            3 => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 52,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 34,
                  'end' => 39,
                ),
                'kind' => 'Name',
                'value' => 'first',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 41,
                  'end' => 45,
                ),
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'loc' => 
                  array (
                    'start' => 41,
                    'end' => 44,
                  ),
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 41,
                      'end' => 44,
                    ),
                    'kind' => 'Name',
                    'value' => 'Int',
                  ),
                ),
              ),
              'defaultValue' => 
              array (
                'loc' => 
                array (
                  'start' => 49,
                  'end' => 52,
                ),
                'kind' => 'IntValue',
                'value' => '100',
              ),
              'directives' => 
              array (
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 33,
                ),
                'kind' => 'StringValue',
                'value' => 'Limits number of fetched items.',
                'block' => false,
              ),
            ),
            4 => 
            array (
              'loc' => 
              array (
                'start' => 4,
                'end' => 61,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 52,
                  'end' => 56,
                ),
                'kind' => 'Name',
                'value' => 'page',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 58,
                  'end' => 61,
                ),
                'kind' => 'NamedType',
                'name' => 
                array (
                  'loc' => 
                  array (
                    'start' => 58,
                    'end' => 61,
                  ),
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'directives' => 
              array (
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 4,
                  'end' => 47,
                ),
                'kind' => 'StringValue',
                'value' => 'The offset from which items are returned.',
                'block' => false,
              ),
            ),
            5 => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 89,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 64,
                  'end' => 71,
                ),
                'kind' => 'Name',
                'value' => 'trashed',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 73,
                  'end' => 80,
                ),
                'kind' => 'NamedType',
                'name' => 
                array (
                  'loc' => 
                  array (
                    'start' => 73,
                    'end' => 80,
                  ),
                  'kind' => 'Name',
                  'value' => 'Trashed',
                ),
              ),
              'directives' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 81,
                    'end' => 89,
                  ),
                  'kind' => 'Directive',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 82,
                      'end' => 89,
                    ),
                    'kind' => 'Name',
                    'value' => 'trashed',
                  ),
                  'arguments' => 
                  array (
                  ),
                ),
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 63,
                ),
                'kind' => 'StringValue',
                'value' => 'Allows to filter if trashed elements should be fetched.',
                'block' => true,
              ),
            ),
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 0,
              'end' => 14,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 13,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 13,
                ),
                'kind' => 'Name',
                'value' => 'FilePaginator',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'paginate',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\GraphQL\\Query@files',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'builder',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'IntValue',
                    'value' => '100',
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'defaultCount',
                  ),
                ),
              ),
            ),
            1 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'view',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\File',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
            2 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'orderBy',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'id',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'column',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'EnumValue',
                    'value' => 'DESC',
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'direction',
                  ),
                ),
              ),
            ),
            3 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'softDeletes',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Get available files',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Indicates what fields are available at the top level of a query operation.',
        'block' => false,
      ),
    ),
    'User' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'User',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Unique primary key.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Non-unique name.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'email',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Unique email address.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'email_verified_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'DateTime',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'When the email was verified.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'When the account was created.',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'DateTime',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'When the account was last updated.',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'permission',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'JSON',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\GraphQL\\Resolvers\\UserResolver@permission',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'CMS editor capabilities',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Account of a person who utilizes this application.',
        'block' => false,
      ),
    ),
    'Upload' => 
    array (
      'kind' => 'ScalarTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Upload',
      ),
      'directives' => 
      array (
        0 => 
        array (
          'kind' => 'Directive',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'scalar',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'Argument',
              'value' => 
              array (
                'kind' => 'StringValue',
                'value' => 'Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\Upload',
                'block' => false,
              ),
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'class',
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'Can be used as an argument to upload files using https://github.com/jaydenseric/graphql-multipart-request-spec',
        'block' => false,
      ),
    ),
    'JSON' => 
    array (
      'kind' => 'ScalarTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'JSON',
      ),
      'directives' => 
      array (
        0 => 
        array (
          'kind' => 'Directive',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'scalar',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'Argument',
              'value' => 
              array (
                'kind' => 'StringValue',
                'value' => 'MLL\\GraphQLScalars\\JSON',
                'block' => false,
              ),
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'class',
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'kind' => 'StringValue',
        'value' => 'JSON data type for JSON strings with arbitrary data',
        'block' => false,
      ),
    ),
    'Publish' => 
    array (
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Publish',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'SCHEDULED',
          ),
          'directives' => 
          array (
          ),
        ),
        1 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'PUBLISHED',
          ),
          'directives' => 
          array (
          ),
        ),
        2 => 
        array (
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'DRAFT',
          ),
          'directives' => 
          array (
          ),
        ),
      ),
    ),
    'RemoveableUpload' => 
    array (
      'kind' => 'UnionTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'RemoveableUpload',
      ),
      'directives' => 
      array (
      ),
      'types' => 
      array (
        0 => 
        array (
          'kind' => 'NamedType',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'Upload',
          ),
        ),
        1 => 
        array (
          'kind' => 'NamedType',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'Boolean',
          ),
        ),
      ),
    ),
    'Page' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Page',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Unique page ID',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'related_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ID',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Translation ID of all pages with the same content in different languages',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'parent_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ID',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ID of the parent page or NULL if it\'s a root page',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'lang',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ISO language code, e.g. \'en\', \'en-GB\' or empty for default language',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'path',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Unique URL segment of the page',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'domain',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Domain name the root page (!) is responsible for',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Short page name for menus',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'title',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Descriptive page title',
            'block' => false,
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'to',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'If not empty, the path or URL the browser is redirected to',
            'block' => false,
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'tag',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Tag name to identify a page, e.g. for the starting point of a navigation structure',
            'block' => false,
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'theme',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Theme name assigned to the page',
            'block' => false,
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'type',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Type of the page for using different theme templates',
            'block' => false,
          ),
        ),
        12 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'JSON',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Arbitrary header data',
            'block' => false,
          ),
        ),
        13 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'config',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'JSON',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Arbitrary configuration settings',
            'block' => false,
          ),
        ),
        14 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'content',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'JSON',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of content',
            'block' => false,
          ),
        ),
        15 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'status',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Visibility status of the page, 0=inactive, 1=visible, 2=hidden in navigation',
            'block' => false,
          ),
        ),
        16 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cache',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Cache lifetime in minutes',
            'block' => false,
          ),
        ),
        17 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'editor',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Name of the last user who added, updated or deleted the page',
            'block' => false,
          ),
        ),
        18 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Date/time value when the page was created',
            'block' => false,
          ),
        ),
        19 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Date/time value when the page was last modified',
            'block' => false,
          ),
        ),
        20 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'deleted_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Date/time value when the page was deleted or NULL if it\'s available',
            'block' => false,
          ),
        ),
        21 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'has',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'If node has children',
            'block' => false,
          ),
        ),
        22 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'parent',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Page',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Relation to the parent page or NULL if it\'s a root page',
            'block' => false,
          ),
        ),
        23 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'ancestors',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Page',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Relation to the ancestors of the current page up to the root page',
            'block' => false,
          ),
        ),
        24 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'elements',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Element',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsToMany',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of shared content elements assigned to the page',
            'block' => false,
          ),
        ),
        25 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'files',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'File',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsToMany',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of files assigned to the page',
            'block' => false,
          ),
        ),
        26 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'latest',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Version',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'morphOne',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Latest version of the page meta data',
            'block' => false,
          ),
        ),
        27 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'published',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Version',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'morphOne',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Published version of the page meta data',
            'block' => false,
          ),
        ),
        28 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'versions',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Version',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'morphMany',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of versions for the page meta data',
            'block' => false,
          ),
        ),
        29 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'children',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 45,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 34,
                  'end' => 39,
                ),
                'kind' => 'Name',
                'value' => 'first',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 41,
                  'end' => 45,
                ),
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'loc' => 
                  array (
                    'start' => 41,
                    'end' => 44,
                  ),
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 41,
                      'end' => 44,
                    ),
                    'kind' => 'Name',
                    'value' => 'Int',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 33,
                ),
                'kind' => 'StringValue',
                'value' => 'Limits number of fetched items.',
                'block' => false,
              ),
            ),
            1 => 
            array (
              'loc' => 
              array (
                'start' => 4,
                'end' => 61,
              ),
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 52,
                  'end' => 56,
                ),
                'kind' => 'Name',
                'value' => 'page',
              ),
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 58,
                  'end' => 61,
                ),
                'kind' => 'NamedType',
                'name' => 
                array (
                  'loc' => 
                  array (
                    'start' => 58,
                    'end' => 61,
                  ),
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'directives' => 
              array (
              ),
              'description' => 
              array (
                'loc' => 
                array (
                  'start' => 4,
                  'end' => 47,
                ),
                'kind' => 'StringValue',
                'value' => 'The offset from which items are returned.',
                'block' => false,
              ),
            ),
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 0,
              'end' => 14,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 0,
                'end' => 13,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 0,
                  'end' => 13,
                ),
                'kind' => 'Name',
                'value' => 'PagePaginator',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'hasMany',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'EnumValue',
                    'value' => 'PAGINATOR',
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'type',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Relation to the children of the current page',
            'block' => false,
          ),
        ),
      ),
    ),
    'PageFilter' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'PageFilter',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'ID',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Unique page IDs',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'related_id',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ID',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Translation ID of all pages with the same content in different languages',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'parent_id',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ID',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ID of the parent page or NULL if it\'s a root page',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'lang',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ISO language code, e.g. \'en\', \'en-GB\' or empty for default language',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'path',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Unique URL segment of the page',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'domain',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Domain name the root page (!) is responsible for',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Short page name for menus',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'title',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Descriptive page title',
            'block' => false,
          ),
        ),
        8 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'to',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'If not empty, the path or URL the browser is redirected to',
            'block' => false,
          ),
        ),
        9 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'tag',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Tag name to identify a page, e.g. for the starting point of a navigation structure',
            'block' => false,
          ),
        ),
        10 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'theme',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Theme name assigned to the page',
            'block' => false,
          ),
        ),
        11 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'type',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Type of the page for using different theme templates',
            'block' => false,
          ),
        ),
        12 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Arbitrary header data',
            'block' => false,
          ),
        ),
        13 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'config',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Arbitrary configuration settings',
            'block' => false,
          ),
        ),
        14 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'content',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of content',
            'block' => false,
          ),
        ),
        15 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'status',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Visibility status of the page, 0=inactive, 1=visible, 2=hidden in navigation',
            'block' => false,
          ),
        ),
        16 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cache',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Cache lifetime in minutes',
            'block' => false,
          ),
        ),
        17 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'editor',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Name of the last user who added, updated or deleted the page',
            'block' => false,
          ),
        ),
        18 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'any',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Search for this string in any text field',
            'block' => false,
          ),
        ),
      ),
    ),
    'PageInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'PageInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'related_id',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'ID',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Translation ID of all pages with the same content in different languages',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'lang',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ISO language code, e.g. \'en\', \'en-GB\' or empty for default language',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'path',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Unique URL segment of the page',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'domain',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Domain name the page is assigned to',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Short page name for menus',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'title',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Descriptive page title',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'to',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'If not empty, the path or URL the browser is redirected to',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'tag',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Tag name to identify a page, e.g. for the starting point of a navigation structure',
            'block' => false,
          ),
        ),
        8 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'theme',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Theme name assigned to the page',
            'block' => false,
          ),
        ),
        9 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'type',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Type of the page for using different theme templates',
            'block' => false,
          ),
        ),
        10 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'meta',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Arbitrary page header',
            'block' => false,
          ),
        ),
        11 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'config',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Arbitrary configuration settings',
            'block' => false,
          ),
        ),
        12 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'content',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of content',
            'block' => false,
          ),
        ),
        13 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'status',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Visibility status of the page, 0=inactive, 1=visible, 2=hidden in navigation',
            'block' => false,
          ),
        ),
        14 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cache',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Cache lifetime in minutes',
            'block' => false,
          ),
        ),
      ),
    ),
    'Element' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Element',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Unique element ID',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'type',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Type of the content element',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'lang',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ISO language code, e.g. \'en\', \'en-GB\' or empty for default language',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Arbitrary string which describes the content element',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'JSON',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Arbitrary content element',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'editor',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Name of the last user who added, updated or deleted the content element',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Date/time value when the content element was created',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Date/time value when the content element was last modified',
            'block' => false,
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'deleted_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Date/time value when the content element was deleted or NULL if it\'s available',
            'block' => false,
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'bypages',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Page',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsToMany',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of pages using the content element',
            'block' => false,
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'byversions',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Version',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsToMany',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of versions using the content element',
            'block' => false,
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'files',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'File',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsToMany',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of files assigned to the content element',
            'block' => false,
          ),
        ),
        12 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'latest',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Version',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'morphOne',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Latest version of the content element',
            'block' => false,
          ),
        ),
        13 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'published',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Version',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'morphOne',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Published version of the content element',
            'block' => false,
          ),
        ),
        14 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'versions',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Version',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'morphMany',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of versioned data for the content element',
            'block' => false,
          ),
        ),
      ),
    ),
    'ElementFilter' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'ElementFilter',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'ID',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Unique element IDs',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'type',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Type of the content elements',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'lang',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ISO language code, e.g. \'en\', \'en-GB\' or empty for default language',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Arbitrary string which describes the content elements',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Arbitrary content elements',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'editor',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Name of the last user who added, updated or deleted the content elements',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'any',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Search for this string in any text field',
            'block' => false,
          ),
        ),
      ),
    ),
    'ElementInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'ElementInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'type',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Type of the content element',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'lang',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ISO language code, e.g. \'en\', \'en-GB\' or empty for default language',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Arbitrary string which describes the element',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'JSON',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Arbitrary content element data',
            'block' => false,
          ),
        ),
      ),
    ),
    'File' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'File',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Unique ID of the stored file',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'mime',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Mime type of the stored file',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'lang',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ISO language code, e.g. \'en\', \'en-GB\' or empty for default language',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Descriptive name of the file',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'description',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Description of the file in different languages with ISO language code as key',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'transcription',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Transcribtion of the file content in different languages with ISO language code as key',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'path',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Relative path to the stored file',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'previews',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'JSON',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Preview images of the stored file if any',
            'block' => false,
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'editor',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Name of the last user who added, updated or deleted the file',
            'block' => false,
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Date/time value when the file was created',
            'block' => false,
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'updated_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Date/time value when the file was last modified',
            'block' => false,
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'deleted_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Date/time value when the file was deleted or NULL if it\'s available',
            'block' => false,
          ),
        ),
        12 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'byelements',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Element',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsToMany',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of elements using the file',
            'block' => false,
          ),
        ),
        13 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'bypages',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Page',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsToMany',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of pages using the file',
            'block' => false,
          ),
        ),
        14 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'byversions',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Version',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsToMany',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of versions using the file',
            'block' => false,
          ),
        ),
        15 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'byversions_count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of versions referencing the file',
            'block' => false,
          ),
        ),
        16 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'latest',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Version',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'morphOne',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Latest version of the file',
            'block' => false,
          ),
        ),
        17 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'published',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Version',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'morphOne',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Published version of the content element',
            'block' => false,
          ),
        ),
        18 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'versions',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Version',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'morphMany',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of versioned data for the file',
            'block' => false,
          ),
        ),
      ),
    ),
    'FileFilter' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'FileFilter',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'ID',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'IDs of the stored files',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'mime',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Mime type of the stored files',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'lang',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ISO language code, e.g. \'en\', \'en-GB\' or empty for default language',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Descriptive name of the stored files',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'editor',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Name of the last user who added, updated or deleted the files',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'any',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Search for this string in any text field',
            'block' => false,
          ),
        ),
      ),
    ),
    'FileInput' => 
    array (
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'FileInput',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'lang',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ISO language code, e.g. \'en\', \'en-GB\' or empty for default language',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'name',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Descriptive name of the stored file',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'path',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'URL or relative path within the storage of the file',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'previews',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Preview images of the stored file if any',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'description',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Description of the file in different languages with ISO language code as key',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'transcription',
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Transcribtion of the file content in different languages with ISO language code as key',
            'block' => false,
          ),
        ),
      ),
    ),
    'Version' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Version',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Unique version ID',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'versionable_id',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ID',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ID of the page or element',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'versionable_type',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Model class name of the versioned item',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'lang',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ISO language code, e.g. \'en\', \'en-GB\' or empty for default language',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'JSON',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Arbitrary versioned data',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'aux',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Arbitrary versioned page config, content and meta data',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'elements',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Element',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'belongsToMany',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of shared content elements assigned to the version',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'files',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'File',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of files used in the data or content property',
            'block' => false,
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'published',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'If versioned is currently published or not',
            'block' => false,
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'publish_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'ISO date when the version should be published',
            'block' => false,
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'editor',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Name of the last user who added, updated or deleted the versioned data',
            'block' => false,
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'created_at',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Date/time value when the versioned data was created',
            'block' => false,
          ),
        ),
      ),
    ),
    'Stats' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'Stats',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'errors',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of errors occurred',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'views',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'StatsNumber',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of page views by day',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'visits',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'StatsNumber',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of page visits by day',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'conversions',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'StatsNumber',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of conversions on page by day',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'durations',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'StatsNumber',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Average page visit duration in seconds by day',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'countries',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'StatsNumber',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of page visits by countries',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'referrers',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'StatsNumber',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of page visits by referring website',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'pagespeed',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'StatsNumber',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Page Speed related values for the page',
            'block' => false,
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'impressions',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'StatsNumber',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of Google search impressions',
            'block' => false,
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'clicks',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'StatsNumber',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of Google search clicks',
            'block' => false,
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'ctrs',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'StatsNumber',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Click-throgh rate in Google search results',
            'block' => false,
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'queries',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'StatsQuery',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Google search queries with stats',
            'block' => false,
          ),
        ),
      ),
    ),
    'StatsNumber' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'StatsNumber',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'key',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Date in ISO format (YYYY-MM-DD) or string',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'value',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Float',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of page views or visits',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'rows',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'ListType',
            'type' => 
            array (
              'kind' => 'NonNullType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'StatsNumber',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'List of individual rows',
            'block' => false,
          ),
        ),
      ),
    ),
    'StatsQuery' => 
    array (
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'kind' => 'Name',
        'value' => 'StatsQuery',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'key',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Query string',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'impressions',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of page impressions in Google Search results',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'clicks',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Number of page clicks in  Google Search results',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'ctr',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Float',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Click through rate in  Google Search (between 0 and 1)',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'position',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Float',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Position in Google Search results',
            'block' => false,
          ),
        ),
      ),
    ),
    'Mutation' => 
    array (
      'loc' => 
      array (
        'start' => 0,
        'end' => 13,
      ),
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 5,
          'end' => 13,
        ),
        'kind' => 'Name',
        'value' => 'Mutation',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cmsLogin',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'email',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'String',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'password',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'String',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'NamedType',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'User',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Authenticated user',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'cmsLogout',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'User',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'guard',
              ),
              'arguments' => 
              array (
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Log out authenticated user',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'compose',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'prompt',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'String',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'context',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'files',
              ),
              'type' => 
              array (
                'kind' => 'ListType',
                'type' => 
                array (
                  'kind' => 'NonNullType',
                  'type' => 
                  array (
                    'kind' => 'NamedType',
                    'name' => 
                    array (
                      'kind' => 'Name',
                      'value' => 'String',
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Generate texts from prompts and passed file IDs',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'erase',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'file',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Upload',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'mask',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Upload',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Remove the part of the image covered by the mask (black: keep, white: erase), returns a base64 encoded image',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'imagine',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'prompt',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'String',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'context',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'files',
              ),
              'type' => 
              array (
                'kind' => 'ListType',
                'type' => 
                array (
                  'kind' => 'NonNullType',
                  'type' => 
                  array (
                    'kind' => 'NamedType',
                    'name' => 
                    array (
                      'kind' => 'Name',
                      'value' => 'String',
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Generate images from prompts and passed file IDs, returns a base64 encoded image',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'inpaint',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'file',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Upload',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'mask',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Upload',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'prompt',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'String',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Edit an image using a mask image and a prompt, returns a base64 encoded image',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'isolate',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'file',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Upload',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Remove image background, returns a base64 encoded image',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'metrics',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'url',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'String',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'days',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'lang',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Stats',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Get page metrics for the last X days',
            'block' => false,
          ),
        ),
        8 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'repaint',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'file',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Upload',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'prompt',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'String',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Edit an image using a prompt, returns a base64 encoded image',
            'block' => false,
          ),
        ),
        9 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'refine',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'prompt',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'String',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'content',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'JSON',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'type',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            3 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'context',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Refine existing page content',
            'block' => false,
          ),
        ),
        10 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'synthesize',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'prompt',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'String',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'context',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'files',
              ),
              'type' => 
              array (
                'kind' => 'ListType',
                'type' => 
                array (
                  'kind' => 'NonNullType',
                  'type' => 
                  array (
                    'kind' => 'NamedType',
                    'name' => 
                    array (
                      'kind' => 'Name',
                      'value' => 'String',
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Synthesize pages and content from prompts and passed file IDs',
            'block' => false,
          ),
        ),
        11 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'transcribe',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'file',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Upload',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'JSON',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Transcribe uploaded audio file',
            'block' => false,
          ),
        ),
        12 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'translate',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'texts',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'ListType',
                  'type' => 
                  array (
                    'kind' => 'NonNullType',
                    'type' => 
                    array (
                      'kind' => 'NamedType',
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'String',
                      ),
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'to',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'String',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'from',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            3 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'context',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'String',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'String',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Translate texts',
            'block' => false,
          ),
        ),
        13 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'uncrop',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'file',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Upload',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'top',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Int',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'right',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            3 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'bottom',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            4 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'left',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Int',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Extend/outpaint image, returns a base64 encoded image',
            'block' => false,
          ),
        ),
        14 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'upscale',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'file',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Upload',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'factor',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'Int',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'String',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Upscale image, returns a base64 encoded image',
            'block' => false,
          ),
        ),
        15 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'addPage',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'PageInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'parent',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'ID',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ref',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'ID',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            3 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'elements',
              ),
              'type' => 
              array (
                'kind' => 'ListType',
                'type' => 
                array (
                  'kind' => 'NonNullType',
                  'type' => 
                  array (
                    'kind' => 'NamedType',
                    'name' => 
                    array (
                      'kind' => 'Name',
                      'value' => 'ID',
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            4 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'files',
              ),
              'type' => 
              array (
                'kind' => 'ListType',
                'type' => 
                array (
                  'kind' => 'NonNullType',
                  'type' => 
                  array (
                    'kind' => 'NamedType',
                    'name' => 
                    array (
                      'kind' => 'Name',
                      'value' => 'ID',
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Page',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'add',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\Page',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Add a new page below the page referenced by \'parent\' and before the page referenced by \'ref\' (or append if NULL)',
            'block' => false,
          ),
        ),
        16 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'movePage',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'parent',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'ID',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'ref',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'ID',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Page',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'move',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\Page',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Move the page below the page referenced by \'parent\' and before the page referenced by \'ref\' (or append if NULL)',
            'block' => false,
          ),
        ),
        17 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'savePage',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'PageInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'elements',
              ),
              'type' => 
              array (
                'kind' => 'ListType',
                'type' => 
                array (
                  'kind' => 'NonNullType',
                  'type' => 
                  array (
                    'kind' => 'NamedType',
                    'name' => 
                    array (
                      'kind' => 'Name',
                      'value' => 'ID',
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            3 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'files',
              ),
              'type' => 
              array (
                'kind' => 'ListType',
                'type' => 
                array (
                  'kind' => 'NonNullType',
                  'type' => 
                  array (
                    'kind' => 'NamedType',
                    'name' => 
                    array (
                      'kind' => 'Name',
                      'value' => 'ID',
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Page',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'save',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\Page',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Update the values of an existing page',
            'block' => false,
          ),
        ),
        18 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'dropPage',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'ListType',
                  'type' => 
                  array (
                    'kind' => 'NonNullType',
                    'type' => 
                    array (
                      'kind' => 'NamedType',
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'ID',
                      ),
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Page',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'drop',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\Page',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Delete an existing page',
            'block' => false,
          ),
        ),
        19 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'keepPage',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'ListType',
                  'type' => 
                  array (
                    'kind' => 'NonNullType',
                    'type' => 
                    array (
                      'kind' => 'NamedType',
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'ID',
                      ),
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Page',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'keep',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\Page',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Restore an existing page',
            'block' => false,
          ),
        ),
        20 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'purgePage',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'ListType',
                  'type' => 
                  array (
                    'kind' => 'NonNullType',
                    'type' => 
                    array (
                      'kind' => 'NamedType',
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'ID',
                      ),
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Page',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'purge',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\Page',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Purge an existing page',
            'block' => false,
          ),
        ),
        21 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'pubPage',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'ListType',
                  'type' => 
                  array (
                    'kind' => 'NonNullType',
                    'type' => 
                    array (
                      'kind' => 'NamedType',
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'ID',
                      ),
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'at',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'DateTime',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Page',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'publish',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\Page',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Publish page data',
            'block' => false,
          ),
        ),
        22 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'addElement',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ElementInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'files',
              ),
              'type' => 
              array (
                'kind' => 'ListType',
                'type' => 
                array (
                  'kind' => 'NonNullType',
                  'type' => 
                  array (
                    'kind' => 'NamedType',
                    'name' => 
                    array (
                      'kind' => 'Name',
                      'value' => 'ID',
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Element',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'add',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\Element',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Add a new shared content element with file references',
            'block' => false,
          ),
        ),
        23 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'saveElement',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ElementInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'files',
              ),
              'type' => 
              array (
                'kind' => 'ListType',
                'type' => 
                array (
                  'kind' => 'NonNullType',
                  'type' => 
                  array (
                    'kind' => 'NamedType',
                    'name' => 
                    array (
                      'kind' => 'Name',
                      'value' => 'ID',
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'Element',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'save',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\Element',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Update an existing shared content element',
            'block' => false,
          ),
        ),
        24 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'dropElement',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'ListType',
                  'type' => 
                  array (
                    'kind' => 'NonNullType',
                    'type' => 
                    array (
                      'kind' => 'NamedType',
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'ID',
                      ),
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Element',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'drop',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\Element',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Delete an existing shared content element',
            'block' => false,
          ),
        ),
        25 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'keepElement',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'ListType',
                  'type' => 
                  array (
                    'kind' => 'NonNullType',
                    'type' => 
                    array (
                      'kind' => 'NamedType',
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'ID',
                      ),
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Element',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'keep',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\Element',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Restore an existing shared content element',
            'block' => false,
          ),
        ),
        26 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'purgeElement',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'ListType',
                  'type' => 
                  array (
                    'kind' => 'NonNullType',
                    'type' => 
                    array (
                      'kind' => 'NamedType',
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'ID',
                      ),
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Element',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'purge',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\Element',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Purge an existing shared content element',
            'block' => false,
          ),
        ),
        27 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'pubElement',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'ListType',
                  'type' => 
                  array (
                    'kind' => 'NonNullType',
                    'type' => 
                    array (
                      'kind' => 'NamedType',
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'ID',
                      ),
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'at',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'DateTime',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Element',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'publish',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\Element',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Publish an existing shared content element',
            'block' => false,
          ),
        ),
        28 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'addFile',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'FileInput',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'file',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Upload',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'preview',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Upload',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'File',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'add',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\File',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Adds a new file upload, optionally with preview and the description of the file',
            'block' => false,
          ),
        ),
        29 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'saveFile',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ID',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'input',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'FileInput',
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            2 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'file',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'Upload',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            3 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'preview',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'RemoveableUpload',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NamedType',
            'name' => 
            array (
              'kind' => 'Name',
              'value' => 'File',
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'save',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\File',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Update an existing file',
            'block' => false,
          ),
        ),
        30 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'dropFile',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'ListType',
                  'type' => 
                  array (
                    'kind' => 'NonNullType',
                    'type' => 
                    array (
                      'kind' => 'NamedType',
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'ID',
                      ),
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'File',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'drop',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\File',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Delete an existing file',
            'block' => false,
          ),
        ),
        31 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'keepFile',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'ListType',
                  'type' => 
                  array (
                    'kind' => 'NonNullType',
                    'type' => 
                    array (
                      'kind' => 'NamedType',
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'ID',
                      ),
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'File',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'keep',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\File',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Restore an existing file',
            'block' => false,
          ),
        ),
        32 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'purgeFile',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'ListType',
                  'type' => 
                  array (
                    'kind' => 'NonNullType',
                    'type' => 
                    array (
                      'kind' => 'NamedType',
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'ID',
                      ),
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'File',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'purge',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\File',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Purge an existing file',
            'block' => false,
          ),
        ),
        33 => 
        array (
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'kind' => 'Name',
            'value' => 'pubFile',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'id',
              ),
              'type' => 
              array (
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'kind' => 'ListType',
                  'type' => 
                  array (
                    'kind' => 'NonNullType',
                    'type' => 
                    array (
                      'kind' => 'NamedType',
                      'name' => 
                      array (
                        'kind' => 'Name',
                        'value' => 'ID',
                      ),
                    ),
                  ),
                ),
              ),
              'directives' => 
              array (
              ),
            ),
            1 => 
            array (
              'kind' => 'InputValueDefinition',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'at',
              ),
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'DateTime',
                ),
              ),
              'directives' => 
              array (
              ),
            ),
          ),
          'type' => 
          array (
            'kind' => 'NonNullType',
            'type' => 
            array (
              'kind' => 'ListType',
              'type' => 
              array (
                'kind' => 'NamedType',
                'name' => 
                array (
                  'kind' => 'Name',
                  'value' => 'File',
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'kind' => 'Directive',
              'name' => 
              array (
                'kind' => 'Name',
                'value' => 'canModel',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => 'publish',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'ability',
                  ),
                ),
                1 => 
                array (
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'kind' => 'StringValue',
                    'value' => '\\Aimeos\\Cms\\Models\\File',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'kind' => 'Name',
                    'value' => 'model',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'kind' => 'StringValue',
            'value' => 'Publish file data',
            'block' => false,
          ),
        ),
      ),
    ),
    'PaginatorInfo' => 
    array (
      'loc' => 
      array (
        'start' => 4,
        'end' => 625,
      ),
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 78,
          'end' => 91,
        ),
        'kind' => 'Name',
        'value' => 'PaginatorInfo',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 101,
            'end' => 157,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 146,
              'end' => 151,
            ),
            'kind' => 'Name',
            'value' => 'count',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 153,
              'end' => 157,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 153,
                'end' => 156,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 153,
                  'end' => 156,
                ),
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 101,
              'end' => 139,
            ),
            'kind' => 'StringValue',
            'value' => 'Number of items in the current page.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 165,
            'end' => 217,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 200,
              'end' => 211,
            ),
            'kind' => 'Name',
            'value' => 'currentPage',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 213,
              'end' => 217,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 213,
                'end' => 216,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 213,
                  'end' => 216,
                ),
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 165,
              'end' => 193,
            ),
            'kind' => 'StringValue',
            'value' => 'Index of the current page.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'loc' => 
          array (
            'start' => 225,
            'end' => 292,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 278,
              'end' => 287,
            ),
            'kind' => 'Name',
            'value' => 'firstItem',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 289,
              'end' => 292,
            ),
            'kind' => 'NamedType',
            'name' => 
            array (
              'loc' => 
              array (
                'start' => 289,
                'end' => 292,
              ),
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 225,
              'end' => 271,
            ),
            'kind' => 'StringValue',
            'value' => 'Index of the first item in the current page.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'loc' => 
          array (
            'start' => 300,
            'end' => 367,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 345,
              'end' => 357,
            ),
            'kind' => 'Name',
            'value' => 'hasMorePages',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 359,
              'end' => 367,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 359,
                'end' => 366,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 359,
                  'end' => 366,
                ),
                'kind' => 'Name',
                'value' => 'Boolean',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 300,
              'end' => 338,
            ),
            'kind' => 'StringValue',
            'value' => 'Are there more pages after this one?',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'loc' => 
          array (
            'start' => 375,
            'end' => 440,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 427,
              'end' => 435,
            ),
            'kind' => 'Name',
            'value' => 'lastItem',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 437,
              'end' => 440,
            ),
            'kind' => 'NamedType',
            'name' => 
            array (
              'loc' => 
              array (
                'start' => 437,
                'end' => 440,
              ),
              'kind' => 'Name',
              'value' => 'Int',
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 375,
              'end' => 420,
            ),
            'kind' => 'StringValue',
            'value' => 'Index of the last item in the current page.',
            'block' => false,
          ),
        ),
        5 => 
        array (
          'loc' => 
          array (
            'start' => 448,
            'end' => 504,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 490,
              'end' => 498,
            ),
            'kind' => 'Name',
            'value' => 'lastPage',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 500,
              'end' => 504,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 500,
                'end' => 503,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 500,
                  'end' => 503,
                ),
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 448,
              'end' => 483,
            ),
            'kind' => 'StringValue',
            'value' => 'Index of the last available page.',
            'block' => false,
          ),
        ),
        6 => 
        array (
          'loc' => 
          array (
            'start' => 512,
            'end' => 559,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 546,
              'end' => 553,
            ),
            'kind' => 'Name',
            'value' => 'perPage',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 555,
              'end' => 559,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 555,
                'end' => 558,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 555,
                  'end' => 558,
                ),
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 512,
              'end' => 539,
            ),
            'kind' => 'StringValue',
            'value' => 'Number of items per page.',
            'block' => false,
          ),
        ),
        7 => 
        array (
          'loc' => 
          array (
            'start' => 567,
            'end' => 619,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 608,
              'end' => 613,
            ),
            'kind' => 'Name',
            'value' => 'total',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 615,
              'end' => 619,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 615,
                'end' => 618,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 615,
                  'end' => 618,
                ),
                'kind' => 'Name',
                'value' => 'Int',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 567,
              'end' => 601,
            ),
            'kind' => 'StringValue',
            'value' => 'Number of total available items.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 4,
          'end' => 68,
        ),
        'kind' => 'StringValue',
        'value' => 'Information about pagination using a fully featured paginator.',
        'block' => false,
      ),
    ),
    'UserPaginator' => 
    array (
      'loc' => 
      array (
        'start' => 4,
        'end' => 391,
      ),
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 47,
          'end' => 60,
        ),
        'kind' => 'Name',
        'value' => 'UserPaginator',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 4,
            'end' => 38,
          ),
          'kind' => 'Directive',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 5,
              'end' => 10,
            ),
            'kind' => 'Name',
            'value' => 'model',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 11,
                'end' => 37,
              ),
              'kind' => 'Argument',
              'value' => 
              array (
                'loc' => 
                array (
                  'start' => 18,
                  'end' => 37,
                ),
                'kind' => 'StringValue',
                'value' => 'App\\Models\\User',
                'block' => false,
              ),
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 11,
                  'end' => 16,
                ),
                'kind' => 'Name',
                'value' => 'class',
              ),
            ),
          ),
        ),
      ),
      'fields' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 71,
            'end' => 247,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 129,
              'end' => 142,
            ),
            'kind' => 'Name',
            'value' => 'paginatorInfo',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 144,
              'end' => 158,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 144,
                'end' => 157,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 144,
                  'end' => 157,
                ),
                'kind' => 'Name',
                'value' => 'PaginatorInfo',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 159,
                'end' => 247,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 160,
                  'end' => 165,
                ),
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 166,
                    'end' => 246,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 176,
                      'end' => 246,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'Nuwave\\Lighthouse\\Pagination\\PaginatorField@paginatorInfoResolver',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 166,
                      'end' => 174,
                    ),
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 71,
              'end' => 120,
            ),
            'kind' => 'StringValue',
            'value' => 'Pagination information about the list of items.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 258,
            'end' => 384,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 290,
              'end' => 294,
            ),
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 296,
              'end' => 304,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 296,
                'end' => 303,
              ),
              'kind' => 'ListType',
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 297,
                  'end' => 302,
                ),
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'loc' => 
                  array (
                    'start' => 297,
                    'end' => 301,
                  ),
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 297,
                      'end' => 301,
                    ),
                    'kind' => 'Name',
                    'value' => 'User',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 305,
                'end' => 384,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 306,
                  'end' => 311,
                ),
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 312,
                    'end' => 383,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 322,
                      'end' => 383,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'Nuwave\\Lighthouse\\Pagination\\PaginatorField@dataResolver',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 312,
                      'end' => 320,
                    ),
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 258,
              'end' => 281,
            ),
            'kind' => 'StringValue',
            'value' => 'A list of User items.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 4,
          'end' => 37,
        ),
        'kind' => 'StringValue',
        'value' => 'A paginated list of User items.',
        'block' => false,
      ),
    ),
    'PagePaginator' => 
    array (
      'loc' => 
      array (
        'start' => 4,
        'end' => 391,
      ),
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 47,
          'end' => 60,
        ),
        'kind' => 'Name',
        'value' => 'PagePaginator',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 4,
            'end' => 46,
          ),
          'kind' => 'Directive',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 5,
              'end' => 10,
            ),
            'kind' => 'Name',
            'value' => 'model',
          ),
          'arguments' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 11,
                'end' => 45,
              ),
              'kind' => 'Argument',
              'value' => 
              array (
                'loc' => 
                array (
                  'start' => 18,
                  'end' => 45,
                ),
                'kind' => 'StringValue',
                'value' => 'Aimeos\\Cms\\Models\\Page',
                'block' => false,
              ),
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 11,
                  'end' => 16,
                ),
                'kind' => 'Name',
                'value' => 'class',
              ),
            ),
          ),
        ),
      ),
      'fields' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 71,
            'end' => 247,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 129,
              'end' => 142,
            ),
            'kind' => 'Name',
            'value' => 'paginatorInfo',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 144,
              'end' => 158,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 144,
                'end' => 157,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 144,
                  'end' => 157,
                ),
                'kind' => 'Name',
                'value' => 'PaginatorInfo',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 159,
                'end' => 247,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 160,
                  'end' => 165,
                ),
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 166,
                    'end' => 246,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 176,
                      'end' => 246,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'Nuwave\\Lighthouse\\Pagination\\PaginatorField@paginatorInfoResolver',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 166,
                      'end' => 174,
                    ),
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 71,
              'end' => 120,
            ),
            'kind' => 'StringValue',
            'value' => 'Pagination information about the list of items.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 258,
            'end' => 384,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 290,
              'end' => 294,
            ),
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 296,
              'end' => 304,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 296,
                'end' => 303,
              ),
              'kind' => 'ListType',
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 297,
                  'end' => 302,
                ),
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'loc' => 
                  array (
                    'start' => 297,
                    'end' => 301,
                  ),
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 297,
                      'end' => 301,
                    ),
                    'kind' => 'Name',
                    'value' => 'Page',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 305,
                'end' => 384,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 306,
                  'end' => 311,
                ),
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 312,
                    'end' => 383,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 322,
                      'end' => 383,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'Nuwave\\Lighthouse\\Pagination\\PaginatorField@dataResolver',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 312,
                      'end' => 320,
                    ),
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 258,
              'end' => 281,
            ),
            'kind' => 'StringValue',
            'value' => 'A list of Page items.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 4,
          'end' => 37,
        ),
        'kind' => 'StringValue',
        'value' => 'A paginated list of Page items.',
        'block' => false,
      ),
    ),
    'ElementPaginator' => 
    array (
      'loc' => 
      array (
        'start' => 4,
        'end' => 403,
      ),
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 50,
          'end' => 66,
        ),
        'kind' => 'Name',
        'value' => 'ElementPaginator',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 77,
            'end' => 253,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 135,
              'end' => 148,
            ),
            'kind' => 'Name',
            'value' => 'paginatorInfo',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 150,
              'end' => 164,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 150,
                'end' => 163,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 150,
                  'end' => 163,
                ),
                'kind' => 'Name',
                'value' => 'PaginatorInfo',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 165,
                'end' => 253,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 166,
                  'end' => 171,
                ),
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 172,
                    'end' => 252,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 182,
                      'end' => 252,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'Nuwave\\Lighthouse\\Pagination\\PaginatorField@paginatorInfoResolver',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 172,
                      'end' => 180,
                    ),
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 77,
              'end' => 126,
            ),
            'kind' => 'StringValue',
            'value' => 'Pagination information about the list of items.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 264,
            'end' => 396,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 299,
              'end' => 303,
            ),
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 305,
              'end' => 316,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 305,
                'end' => 315,
              ),
              'kind' => 'ListType',
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 306,
                  'end' => 314,
                ),
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'loc' => 
                  array (
                    'start' => 306,
                    'end' => 313,
                  ),
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 306,
                      'end' => 313,
                    ),
                    'kind' => 'Name',
                    'value' => 'Element',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 317,
                'end' => 396,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 318,
                  'end' => 323,
                ),
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 324,
                    'end' => 395,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 334,
                      'end' => 395,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'Nuwave\\Lighthouse\\Pagination\\PaginatorField@dataResolver',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 324,
                      'end' => 332,
                    ),
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 264,
              'end' => 290,
            ),
            'kind' => 'StringValue',
            'value' => 'A list of Element items.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 4,
          'end' => 40,
        ),
        'kind' => 'StringValue',
        'value' => 'A paginated list of Element items.',
        'block' => false,
      ),
    ),
    'FilePaginator' => 
    array (
      'loc' => 
      array (
        'start' => 4,
        'end' => 391,
      ),
      'kind' => 'ObjectTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 47,
          'end' => 60,
        ),
        'kind' => 'Name',
        'value' => 'FilePaginator',
      ),
      'interfaces' => 
      array (
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 71,
            'end' => 247,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 129,
              'end' => 142,
            ),
            'kind' => 'Name',
            'value' => 'paginatorInfo',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 144,
              'end' => 158,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 144,
                'end' => 157,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 144,
                  'end' => 157,
                ),
                'kind' => 'Name',
                'value' => 'PaginatorInfo',
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 159,
                'end' => 247,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 160,
                  'end' => 165,
                ),
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 166,
                    'end' => 246,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 176,
                      'end' => 246,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'Nuwave\\Lighthouse\\Pagination\\PaginatorField@paginatorInfoResolver',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 166,
                      'end' => 174,
                    ),
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 71,
              'end' => 120,
            ),
            'kind' => 'StringValue',
            'value' => 'Pagination information about the list of items.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 258,
            'end' => 384,
          ),
          'kind' => 'FieldDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 290,
              'end' => 294,
            ),
            'kind' => 'Name',
            'value' => 'data',
          ),
          'arguments' => 
          array (
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 296,
              'end' => 304,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 296,
                'end' => 303,
              ),
              'kind' => 'ListType',
              'type' => 
              array (
                'loc' => 
                array (
                  'start' => 297,
                  'end' => 302,
                ),
                'kind' => 'NonNullType',
                'type' => 
                array (
                  'loc' => 
                  array (
                    'start' => 297,
                    'end' => 301,
                  ),
                  'kind' => 'NamedType',
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 297,
                      'end' => 301,
                    ),
                    'kind' => 'Name',
                    'value' => 'File',
                  ),
                ),
              ),
            ),
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 305,
                'end' => 384,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 306,
                  'end' => 311,
                ),
                'kind' => 'Name',
                'value' => 'field',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 312,
                    'end' => 383,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 322,
                      'end' => 383,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'Nuwave\\Lighthouse\\Pagination\\PaginatorField@dataResolver',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 312,
                      'end' => 320,
                    ),
                    'kind' => 'Name',
                    'value' => 'resolver',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 258,
              'end' => 281,
            ),
            'kind' => 'StringValue',
            'value' => 'A list of File items.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 4,
          'end' => 37,
        ),
        'kind' => 'StringValue',
        'value' => 'A paginated list of File items.',
        'block' => false,
      ),
    ),
    'QueryElementsSortColumn' => 
    array (
      'loc' => 
      array (
        'start' => 0,
        'end' => 214,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 53,
          'end' => 76,
        ),
        'kind' => 'Name',
        'value' => 'QueryElementsSortColumn',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 83,
            'end' => 104,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 83,
              'end' => 85,
            ),
            'kind' => 'Name',
            'value' => 'ID',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 86,
                'end' => 104,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 87,
                  'end' => 91,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 92,
                    'end' => 103,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 99,
                      'end' => 103,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'id',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 92,
                      'end' => 97,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 105,
            'end' => 130,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 105,
              'end' => 109,
            ),
            'kind' => 'Name',
            'value' => 'LANG',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 110,
                'end' => 130,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 111,
                  'end' => 115,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 116,
                    'end' => 129,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 123,
                      'end' => 129,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'lang',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 116,
                      'end' => 121,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
        ),
        2 => 
        array (
          'loc' => 
          array (
            'start' => 131,
            'end' => 156,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 131,
              'end' => 135,
            ),
            'kind' => 'Name',
            'value' => 'NAME',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 136,
                'end' => 156,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 137,
                  'end' => 141,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 142,
                    'end' => 155,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 149,
                      'end' => 155,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'name',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 142,
                      'end' => 147,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
        ),
        3 => 
        array (
          'loc' => 
          array (
            'start' => 157,
            'end' => 182,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 157,
              'end' => 161,
            ),
            'kind' => 'Name',
            'value' => 'TYPE',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 162,
                'end' => 182,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 163,
                  'end' => 167,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 168,
                    'end' => 181,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 175,
                      'end' => 181,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'type',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 168,
                      'end' => 173,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
        ),
        4 => 
        array (
          'loc' => 
          array (
            'start' => 183,
            'end' => 212,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 183,
              'end' => 189,
            ),
            'kind' => 'Name',
            'value' => 'EDITOR',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 190,
                'end' => 212,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 191,
                  'end' => 195,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 196,
                    'end' => 211,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 203,
                      'end' => 211,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'editor',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 196,
                      'end' => 201,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 0,
          'end' => 47,
        ),
        'kind' => 'StringValue',
        'value' => 'Allowed column names for Query.elements.sort.',
        'block' => false,
      ),
    ),
    'QueryElementsSortOrderByClause' => 
    array (
      'loc' => 
      array (
        'start' => 12,
        'end' => 318,
      ),
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 73,
          'end' => 103,
        ),
        'kind' => 'Name',
        'value' => 'QueryElementsSortOrderByClause',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 122,
            'end' => 210,
          ),
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 178,
              'end' => 184,
            ),
            'kind' => 'Name',
            'value' => 'column',
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 186,
              'end' => 210,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 186,
                'end' => 209,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 186,
                  'end' => 209,
                ),
                'kind' => 'Name',
                'value' => 'QueryElementsSortColumn',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 122,
              'end' => 161,
            ),
            'kind' => 'StringValue',
            'value' => 'The column that is used for ordering.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 228,
            'end' => 304,
          ),
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 287,
              'end' => 292,
            ),
            'kind' => 'Name',
            'value' => 'order',
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 294,
              'end' => 304,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 294,
                'end' => 303,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 294,
                  'end' => 303,
                ),
                'kind' => 'Name',
                'value' => 'SortOrder',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 228,
              'end' => 270,
            ),
            'kind' => 'StringValue',
            'value' => 'The direction that is used for ordering.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 12,
          'end' => 54,
        ),
        'kind' => 'StringValue',
        'value' => 'Order by clause for Query.elements.sort.',
        'block' => false,
      ),
    ),
    'QueryFilesSortColumn' => 
    array (
      'loc' => 
      array (
        'start' => 0,
        'end' => 258,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 50,
          'end' => 70,
        ),
        'kind' => 'Name',
        'value' => 'QueryFilesSortColumn',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 77,
            'end' => 98,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 77,
              'end' => 79,
            ),
            'kind' => 'Name',
            'value' => 'ID',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 80,
                'end' => 98,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 81,
                  'end' => 85,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 86,
                    'end' => 97,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 93,
                      'end' => 97,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'id',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 86,
                      'end' => 91,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 99,
            'end' => 124,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 99,
              'end' => 103,
            ),
            'kind' => 'Name',
            'value' => 'NAME',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 104,
                'end' => 124,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 105,
                  'end' => 109,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 110,
                    'end' => 123,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 117,
                      'end' => 123,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'name',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 110,
                      'end' => 115,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
        ),
        2 => 
        array (
          'loc' => 
          array (
            'start' => 125,
            'end' => 150,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 125,
              'end' => 129,
            ),
            'kind' => 'Name',
            'value' => 'MIME',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 130,
                'end' => 150,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 131,
                  'end' => 135,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 136,
                    'end' => 149,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 143,
                      'end' => 149,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'mime',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 136,
                      'end' => 141,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
        ),
        3 => 
        array (
          'loc' => 
          array (
            'start' => 151,
            'end' => 176,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 151,
              'end' => 155,
            ),
            'kind' => 'Name',
            'value' => 'LANG',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 156,
                'end' => 176,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 157,
                  'end' => 161,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 162,
                    'end' => 175,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 169,
                      'end' => 175,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'lang',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 162,
                      'end' => 167,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
        ),
        4 => 
        array (
          'loc' => 
          array (
            'start' => 177,
            'end' => 206,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 177,
              'end' => 183,
            ),
            'kind' => 'Name',
            'value' => 'EDITOR',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 184,
                'end' => 206,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 185,
                  'end' => 189,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 190,
                    'end' => 205,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 197,
                      'end' => 205,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'editor',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 190,
                      'end' => 195,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
        ),
        5 => 
        array (
          'loc' => 
          array (
            'start' => 207,
            'end' => 256,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 207,
              'end' => 223,
            ),
            'kind' => 'Name',
            'value' => 'BYVERSIONS_COUNT',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 224,
                'end' => 256,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 225,
                  'end' => 229,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 230,
                    'end' => 255,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 237,
                      'end' => 255,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'byversions_count',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 230,
                      'end' => 235,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 0,
          'end' => 44,
        ),
        'kind' => 'StringValue',
        'value' => 'Allowed column names for Query.files.sort.',
        'block' => false,
      ),
    ),
    'QueryFilesSortOrderByClause' => 
    array (
      'loc' => 
      array (
        'start' => 12,
        'end' => 309,
      ),
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 70,
          'end' => 97,
        ),
        'kind' => 'Name',
        'value' => 'QueryFilesSortOrderByClause',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 116,
            'end' => 201,
          ),
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 172,
              'end' => 178,
            ),
            'kind' => 'Name',
            'value' => 'column',
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 180,
              'end' => 201,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 180,
                'end' => 200,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 180,
                  'end' => 200,
                ),
                'kind' => 'Name',
                'value' => 'QueryFilesSortColumn',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 116,
              'end' => 155,
            ),
            'kind' => 'StringValue',
            'value' => 'The column that is used for ordering.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 219,
            'end' => 295,
          ),
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 278,
              'end' => 283,
            ),
            'kind' => 'Name',
            'value' => 'order',
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 285,
              'end' => 295,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 285,
                'end' => 294,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 285,
                  'end' => 294,
                ),
                'kind' => 'Name',
                'value' => 'SortOrder',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 219,
              'end' => 261,
            ),
            'kind' => 'StringValue',
            'value' => 'The direction that is used for ordering.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 12,
          'end' => 51,
        ),
        'kind' => 'StringValue',
        'value' => 'Order by clause for Query.files.sort.',
        'block' => false,
      ),
    ),
    'SortOrder' => 
    array (
      'loc' => 
      array (
        'start' => 21,
        'end' => 301,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 91,
          'end' => 100,
        ),
        'kind' => 'Name',
        'value' => 'SortOrder',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 127,
            'end' => 189,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 186,
              'end' => 189,
            ),
            'kind' => 'Name',
            'value' => 'ASC',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 127,
              'end' => 161,
            ),
            'kind' => 'StringValue',
            'value' => 'Sort records in ascending order.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 215,
            'end' => 279,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 275,
              'end' => 279,
            ),
            'kind' => 'Name',
            'value' => 'DESC',
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 215,
              'end' => 250,
            ),
            'kind' => 'StringValue',
            'value' => 'Sort records in descending order.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 21,
          'end' => 65,
        ),
        'kind' => 'StringValue',
        'value' => 'Directions for ordering a list of records.',
        'block' => false,
      ),
    ),
    'OrderByRelationAggregateFunction' => 
    array (
      'loc' => 
      array (
        'start' => 21,
        'end' => 276,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 125,
          'end' => 157,
        ),
        'kind' => 'Name',
        'value' => 'OrderByRelationAggregateFunction',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 184,
            'end' => 254,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 227,
              'end' => 232,
            ),
            'kind' => 'Name',
            'value' => 'COUNT',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 233,
                'end' => 254,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 234,
                  'end' => 238,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 239,
                    'end' => 253,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 246,
                      'end' => 253,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'count',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 239,
                      'end' => 244,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 184,
              'end' => 202,
            ),
            'kind' => 'StringValue',
            'value' => 'Amount of items.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 21,
          'end' => 99,
        ),
        'kind' => 'StringValue',
        'value' => 'Aggregate functions when ordering by a relation without specifying a column.',
        'block' => false,
      ),
    ),
    'OrderByRelationWithColumnAggregateFunction' => 
    array (
      'loc' => 
      array (
        'start' => 21,
        'end' => 616,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 123,
          'end' => 165,
        ),
        'kind' => 'Name',
        'value' => 'OrderByRelationWithColumnAggregateFunction',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 192,
            'end' => 250,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 227,
              'end' => 230,
            ),
            'kind' => 'Name',
            'value' => 'AVG',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 231,
                'end' => 250,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 232,
                  'end' => 236,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 237,
                    'end' => 249,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 244,
                      'end' => 249,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'avg',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 237,
                      'end' => 242,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 192,
              'end' => 202,
            ),
            'kind' => 'StringValue',
            'value' => 'Average.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 276,
            'end' => 334,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 311,
              'end' => 314,
            ),
            'kind' => 'Name',
            'value' => 'MIN',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 315,
                'end' => 334,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 316,
                  'end' => 320,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 321,
                    'end' => 333,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 328,
                      'end' => 333,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'min',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 321,
                      'end' => 326,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 276,
              'end' => 286,
            ),
            'kind' => 'StringValue',
            'value' => 'Minimum.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'loc' => 
          array (
            'start' => 360,
            'end' => 418,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 395,
              'end' => 398,
            ),
            'kind' => 'Name',
            'value' => 'MAX',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 399,
                'end' => 418,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 400,
                  'end' => 404,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 405,
                    'end' => 417,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 412,
                      'end' => 417,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'max',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 405,
                      'end' => 410,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 360,
              'end' => 370,
            ),
            'kind' => 'StringValue',
            'value' => 'Maximum.',
            'block' => false,
          ),
        ),
        3 => 
        array (
          'loc' => 
          array (
            'start' => 444,
            'end' => 498,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 475,
              'end' => 478,
            ),
            'kind' => 'Name',
            'value' => 'SUM',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 479,
                'end' => 498,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 480,
                  'end' => 484,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 485,
                    'end' => 497,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 492,
                      'end' => 497,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'sum',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 485,
                      'end' => 490,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 444,
              'end' => 450,
            ),
            'kind' => 'StringValue',
            'value' => 'Sum.',
            'block' => false,
          ),
        ),
        4 => 
        array (
          'loc' => 
          array (
            'start' => 524,
            'end' => 594,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 567,
              'end' => 572,
            ),
            'kind' => 'Name',
            'value' => 'COUNT',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 573,
                'end' => 594,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 574,
                  'end' => 578,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 579,
                    'end' => 593,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 586,
                      'end' => 593,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'count',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 579,
                      'end' => 584,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 524,
              'end' => 542,
            ),
            'kind' => 'StringValue',
            'value' => 'Amount of items.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 21,
          'end' => 97,
        ),
        'kind' => 'StringValue',
        'value' => 'Aggregate functions when ordering by a relation that may specify a column.',
        'block' => false,
      ),
    ),
    'OrderByClause' => 
    array (
      'loc' => 
      array (
        'start' => 12,
        'end' => 278,
      ),
      'kind' => 'InputObjectTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 67,
          'end' => 80,
        ),
        'kind' => 'Name',
        'value' => 'OrderByClause',
      ),
      'directives' => 
      array (
      ),
      'fields' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 99,
            'end' => 170,
          ),
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 155,
              'end' => 161,
            ),
            'kind' => 'Name',
            'value' => 'column',
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 163,
              'end' => 170,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 163,
                'end' => 169,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 163,
                  'end' => 169,
                ),
                'kind' => 'Name',
                'value' => 'String',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 99,
              'end' => 138,
            ),
            'kind' => 'StringValue',
            'value' => 'The column that is used for ordering.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 188,
            'end' => 264,
          ),
          'kind' => 'InputValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 247,
              'end' => 252,
            ),
            'kind' => 'Name',
            'value' => 'order',
          ),
          'type' => 
          array (
            'loc' => 
            array (
              'start' => 254,
              'end' => 264,
            ),
            'kind' => 'NonNullType',
            'type' => 
            array (
              'loc' => 
              array (
                'start' => 254,
                'end' => 263,
              ),
              'kind' => 'NamedType',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 254,
                  'end' => 263,
                ),
                'kind' => 'Name',
                'value' => 'SortOrder',
              ),
            ),
          ),
          'directives' => 
          array (
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 188,
              'end' => 230,
            ),
            'kind' => 'StringValue',
            'value' => 'The direction that is used for ordering.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 12,
          'end' => 48,
        ),
        'kind' => 'StringValue',
        'value' => 'Allows ordering a list of records.',
        'block' => false,
      ),
    ),
    'Trashed' => 
    array (
      'loc' => 
      array (
        'start' => 25,
        'end' => 530,
      ),
      'kind' => 'EnumTypeDefinition',
      'name' => 
      array (
        'loc' => 
        array (
          'start' => 128,
          'end' => 135,
        ),
        'kind' => 'Name',
        'value' => 'Trashed',
      ),
      'directives' => 
      array (
      ),
      'values' => 
      array (
        0 => 
        array (
          'loc' => 
          array (
            'start' => 166,
            'end' => 250,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 225,
              'end' => 229,
            ),
            'kind' => 'Name',
            'value' => 'ONLY',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 230,
                'end' => 250,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 231,
                  'end' => 235,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 236,
                    'end' => 249,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 243,
                      'end' => 249,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'only',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 236,
                      'end' => 241,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 166,
              'end' => 196,
            ),
            'kind' => 'StringValue',
            'value' => 'Only return trashed results.',
            'block' => false,
          ),
        ),
        1 => 
        array (
          'loc' => 
          array (
            'start' => 280,
            'end' => 380,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 355,
              'end' => 359,
            ),
            'kind' => 'Name',
            'value' => 'WITH',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 360,
                'end' => 380,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 361,
                  'end' => 365,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 366,
                    'end' => 379,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 373,
                      'end' => 379,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'with',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 366,
                      'end' => 371,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 280,
              'end' => 326,
            ),
            'kind' => 'StringValue',
            'value' => 'Return both trashed and non-trashed results.',
            'block' => false,
          ),
        ),
        2 => 
        array (
          'loc' => 
          array (
            'start' => 410,
            'end' => 504,
          ),
          'kind' => 'EnumValueDefinition',
          'name' => 
          array (
            'loc' => 
            array (
              'start' => 473,
              'end' => 480,
            ),
            'kind' => 'Name',
            'value' => 'WITHOUT',
          ),
          'directives' => 
          array (
            0 => 
            array (
              'loc' => 
              array (
                'start' => 481,
                'end' => 504,
              ),
              'kind' => 'Directive',
              'name' => 
              array (
                'loc' => 
                array (
                  'start' => 482,
                  'end' => 486,
                ),
                'kind' => 'Name',
                'value' => 'enum',
              ),
              'arguments' => 
              array (
                0 => 
                array (
                  'loc' => 
                  array (
                    'start' => 487,
                    'end' => 503,
                  ),
                  'kind' => 'Argument',
                  'value' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 494,
                      'end' => 503,
                    ),
                    'kind' => 'StringValue',
                    'value' => 'without',
                    'block' => false,
                  ),
                  'name' => 
                  array (
                    'loc' => 
                    array (
                      'start' => 487,
                      'end' => 492,
                    ),
                    'kind' => 'Name',
                    'value' => 'value',
                  ),
                ),
              ),
            ),
          ),
          'description' => 
          array (
            'loc' => 
            array (
              'start' => 410,
              'end' => 444,
            ),
            'kind' => 'StringValue',
            'value' => 'Only return non-trashed results.',
            'block' => false,
          ),
        ),
      ),
      'description' => 
      array (
        'loc' => 
        array (
          'start' => 25,
          'end' => 98,
        ),
        'kind' => 'StringValue',
        'value' => 'Specify if you want to include or exclude trashed results from a query.',
        'block' => false,
      ),
    ),
  ),
  'directives' => 
  array (
  ),
  'classNameToObjectTypeName' => 
  array (
  ),
  'schemaExtensions' => 
  array (
  ),
  'hash' => '3c8a20ed25ef208baac1e33d4b7c2660f041b7516c629c409e17f4ad13399661',
);