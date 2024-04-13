# GUIFlow

GUIFlow is a no-code workflow builder.

## Introduction

GUIFlow workflows are based on Directed Acyclic Graphs.

Directed Acyclic Graph (DAG) workflows represent a series of steps executed in a specific order where each step may depend on the completion of previous steps, but there are no cycles or loops. 
This structure ensures that workflows are predictable and can be executed efficiently, especially in processing pipelines where tasks need to be coordinated without overlapping or rerunning steps unnecessarily.

## Overview of GUIFlow implementation

The workflow is represented as JSON which is defined by a given JSON schema:
- This schema defines a structure for representing workflows as arrays of rules. 
- Each rule encapsulates a series of actions that are executed if certain conditions are met. 
- This schema is designed to facilitate complex workflow execution tasks in a structured and scalable manner.

## Structure of the Schema

### Rules

The schema consists of an array of "rules," each of which defines a part of the workflow. Below is a breakdown of the top-level elements within each rule:

- **`rule`**: A string identifier for the rule, providing a human-readable name.
- **`stage`**: A unique identifier for the stage of this rule within the workflow. This is used to track the progression and dependencies of the workflow.
- **`description`**: A human-readable description of what the rule is designed to achieve.
- **`dependencies`**: An array of action stage identifiers that the current rule depends on. The current rule stage will only be executed after all listed actions have been completed. NB: rule dependencies CANNOT be other rules but rather, must be actions.
- **`skip`**: Indicates whether the execution of the rule should be skipped (`"1"`) or not (`"0"`). Skipping a rule means all actions under it are not executed.
- **`condition`**: Defines a condition that must be true for the rule's actions to be executed.
- **`actions`**: An array of actions to be executed as part of the rule. Actions are further described in detail below

### Actions

Actions within a rule represent the specific operations to be executed. Each action has several key properties:

- **`action`**: The type of action to be performed, picked from the set of pre-defined actions: `add`, `subtract`, `multiply`, `divide`, `set`, `function`,`delete`, `removePath`.
- **`description`**: Details description of what the action does or is intended to accomplish.
- **`stage`**: Similar to the rule's stage, providing a unique identifier for the action within the workflow.
- **`dependencies`**: A list of action stage identifiers that specifies other actions that must be completed before this action can commence. By default, all actions depend on their parent rule condition being executed. This dependency is inherent and does not need to be specified or defined and cannot be overriden.
- **`skip`**: Determines if the action should be skipped, similar to the skip in the rule.
- **`params`**: Parameters required to execute the action, which vary depending on the type of action being called. This is described in detail below:

### Action Params

#### Arithmetic Actions

Arithmetic actions perform basic mathematical operations on numeric data. 
These actions are fundamental in data manipulation, allowing for the computation of new values based on existing data.

Arithmetic actions can be any of: `add`, `subtract`, `multiply`, and `divide` and typically take 2 parameters: the first and second operands.

##### Parameters for Arithmetic Actions:
- **`path`**: This is the location within the data that forms the first operand. It should be a valid JSON path that points to a numeric field.
- **`value`**: The static number that will be used as the second operand in the arithmetic operation. This could be a literal number or a number derived from another part of the data.
- **`valueFromField`**: An alternative to `value`, this parameter specifies a path to a field from which the number for the second operand will be dynamically retrieved. This allows for operations that rely on variable data within the dataset.
- **`newField`**: Specifies the field where the result of the arithmetic operation will be stored. This can be an existing field that will be overwritten or a new field that will be added to the data. If not set, results will be updated in `path`

#### Set Action

Set actions are used to assign a new value to a specified path in the data. This can be used to update data fields, add new information, or transform data based on specific conditions.

The value for `action` is `set`.

##### Parameters for Set Action:
- **`path`**: The JSON path where the value will be set. This can point to any part of the data structure, modifying it as needed. Can be left blank if we want the data to be set on a `newField`
- **`value`**: The new literal data to assign to the path. This can be any data type appropriate to the data structure, including strings, numbers, arrays, and objects.
- **`valueFromField`**: This parameter allows the new value to be dynamically obtained from another field in the data. If specified, it overrides the `value` parameter.
- **`valueMapping`**: A key-value dictionary map of old values (keys) to new values (value), used when transforming data based on existing content. This is useful in scenarios where you need to standardize or normalize data. a {"key":"value"} map means that if the data is currently "key", replace it with "value". If specified, it overrides the `value` and `valueFromField` parameters.
- **`conditionalValue`**: Allows setting different values based on various conditions. Each condition must be specified along with the value to set if the condition is true, providing a powerful tool for dynamic data manipulation. It's kind of a multiple `if-then` clauses or `case` clause implementation where multiple values are given and the value to be set will depend on which condition is true. If specified, it overrides the `value`, `valueFromField` and `valueMapping` parameters.
- **`newField`**: If provided, this parameter specifies a new field to create with the set value, allowing for expansions of the data structure. If not set, results will be updated in `path`

#### Delete or Remove Path Actions

Delete or remove path actions are used to remove data from a dataset based on the specified path. This can be crucial for data cleaning, memory management, privacy requirements, or preparing data for further processing.

The action `delete` will only remove the data while retaining the path to the data while `remove_path` will remove both the data and the path to that data.

##### Parameters for Delete or Remove Path Actions:
- **`path`**: The JSON path that points to the part of the data to be removed. This specifies the target data elements that will be deleted from the dataset.


#### Function Action

Function actions invoke specific functions defined within the ecosystem. These functions can be standard data processing functions or custom user defined functions designed to perform complex data transformations, analyses or tasks.

##### Parameters for Function Actions:
- **`path`**: The JSON path to the data that the function will operate on. This is always set as the first parameter to the function. Where the function expects more parameters, they can be defined in the `args` section defined below
- **`function`**: Specifies the name of the function to be executed. This should match a function available in the system's function library.
- **`args`**: A collection of arguments or parameters required by the function. The structure and nature of these arguments depend on the specific function being called.
- **`newField`**: Indicates where the result of the function's operation should be stored within the data. This could be used to create a new data element or update an existing one. If not set, results will be updated in `path`
- **`strict`**: Determines when to update the result from the output. If strict is true, we only update the function's result when it is not empty. This ensures we do not perform an update when the function returns an empty value or an error.
- **`condition`**: An optional condition that controls when the function is executed, adding a layer of logic to the function application. This is needed only for the functions that support conditional execution.

For a detailed view of the list of supported functions and their defined arguments, please check the [function parameter schema documentation](schema_doc.html) for more details.

## A Detailed Overview of `PATH`

Path Expressions allow you to navigate through and manipulate data by specifying paths to data within them. This is a crucial component of how the entire workflows operate and how data is defined.
We use path expressions in various sections such as to define `path`, `valueFromField`, `newField`, `conditions` and even within function arguments using the key `path`. 

Whenever a path expression is defined in the workflow definition, it is resolved to get the current data within that path as at the time of execution. 

The path expression follows JSON Path Syntax as described below:

### Basic JSON Path Syntax

- **Object Dot notation**: `object.property` — accesses a property of an object.
- **Array Dot notation**: `array.index` — accesses an element by index within an array.
- **Wildcard**: `*` — matches any element or property at a particular level.

### Example JSON Data Structures and Path Usage

#### 1. Simple JSON Object

##### Sample Data
```json
{
  "name": "John Doe",
  "age": 30,
  "address": {
    "street": "123 Main St",
    "city": "Anytown"
  }
}
```

##### Accessing Data
- **Path**: `name`
    - **Result**: "John Doe"
- **Path**: `address.city`
    - **Result**: "Anytown"

#### 2. JSON Array of Objects

##### Sample Data
```json
{
  "employees": [
    {"name": "John Doe", "age": 30},
    {"name": "Jane Doe", "age": 25}
  ]
}
```

##### Accessing Data
- **Path**: `employees.0.name`
    - **Result**: "John Doe"
- **Path**: `employees.1.age`
    - **Result**: 25

#### 3. Nested Arrays and Objects

##### Sample Data
```json
{
  "school": "Example High",
  "classes": [
    {
      "name": "Math",
      "students": [
        {"name": "John", "age": 16},
        {"name": "Jane", "age": 15}
      ]
    },
    {
      "name": "Science",
      "students": [
        {"name": "Emily", "age": 14},
        {"name": "Dylan", "age": 16}
      ]
    }
  ]
}
```

##### Accessing Data
- **Path**: `classes.0.students.1.name`
    - **Result**: "Jane"
- **Path**: `classes.*.name`
    - **Result**: ["Math", "Science"]
- **Path**: `classes.1.students.*.name`
    - **Result**: ["Emily", "Dylan"]

#### Advanced Path Usage: Nested Arrays Within Nested Objects

##### Sample Data
```json
{
  "company": "Tech Innovations",
  "departments": [
    {
      "name": "Development",
      "teams": [
        {
          "name": "Frontend",
          "members": [
            {"name": "Alice", "role": "Developer"},
            {"name": "Bob", "role": "Designer"}
          ]
        },
        {
          "name": "Backend",
          "members": [
            {"name": "Charlie", "role": "Developer"},
            {"name": "David", "role": "Database Admin"}
          ]
        }
      ]
    },
    {
      "name": "Marketing",
      "teams": [
        {
          "name": "Digital",
          "members": [
            {"name": "Eve", "role": "SEO Specialist"},
            {"name": "Frank", "role": "Content Writer"}
          ]
        }
      ]
    }
  ]
}
```

##### Accessing Data
- **Path**: `departments.0.teams.1.members.*.name`
    - **Result**: ["Charlie", "David"]
- **Path**: `departments.*.teams.*.members.0.role`
    - **Result**: ["Developer", "Developer", "SEO Specialist"]


## Special Understanding of Model/Schema Mapping Function in GUIFlow

The `model_mapping` function is a powerful tool used to transform data structures from one format or schema to another. 

This functionality is especially useful when interfacing with external systems that require data in a specific format or when data needs restructuring to meet the needs of different parts of an application.

Model mapping can involve restructuring, redacting, or enriching the data.

Generally, this tool is instrumental in ensuring data compatibility and restructuring across different systems and processes within a technology stack, facilitating seamless data integration and management.

### How Model Mapping Works

Model mapping involves defining a mapping schema defining new data paths and the original data paths from which to pull data. 
These data paths follow the same structure as described section above.

The mapping schema can be defined in either of these two different ways:
- **key-value pair** format where the key represents the new data path and the value representing the original data paths.
- **list of dictionaries** where each dictionary defines a `from_path` and `to_path` where from_path represents the source data paths and to_path represents the new data paths.

### Schema Definition for using `model_mapping` in Function Action

The `model_mapping` function in Function Action takes two arguments: `model_mapping` (required) and `inverted` (optional).

- **`model_mapping`**: The mapping schema which can be an object or array that defines how data paths are translated from one model to another.
  - **Object form**: Direct key-value pairs where each key is a new path, and each value is the original path from which to pull data.
  - **Array form**: A list of dictionaries, each specifying `from_path` and `to_path`, providing a clear from-to mapping relationship.
- **`inverted`**: A boolean that when set to true, reverses the direction of the mapping, using model mapping keys as the source paths and values as the new data paths.

### Examples of Using Model Mapping

#### 1. Simple Data Structure Transformation

##### Original Data
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john.doe@example.com"
}
```

###### Model Mapping Schema
```json
{
  "model_mapping": {
    "name.first": "first_name",
    "name.last": "last_name",
    "contact.email": "email"
  },
  "inverted": false
}
```

###### Transformed Data
```json
{
  "name": {
    "first": "John",
    "last": "Doe"
  },
  "contact": {
    "email": "john.doe@example.com"
  }
}
```

This example demonstrates a straightforward transformation, restructuring flat data into a more nested format.

#### 2. Complex Nested Data Transformation

##### Original Data
```json
{
  "user": {
    "details": {
      "firstName": "Jane",
      "lastName": "Smith"
    },
    "contactInfo": {
      "emails": [
        "jane.smith@work.com",
        "jane.smith@personal.com"
      ]
    }
  }
}
```

###### Model Mapping
```json
{
  "model_mapping": [
    {
      "from_path": "user.details.firstName",
      "to_path": "profile.first_name"
    },
    {
      "from_path": "user.details.lastName",
      "to_path": "profile.last_name"
    },
    {
      "from_path": "user.contactInfo.emails.0",
      "to_path": "contact.work_email"
    },
    {
      "from_path": "user.contactInfo.emails.1",
      "to_path": "contact.personal_email"
    }
  ],
  "inverted": false
}
```

###### Transformed Data
```json
{
  "profile": {
    "first_name": "Jane",
    "last_name": "Smith"
  },
  "contact": {
    "work_email": "jane.smith@work.com",
    "personal_email": "jane.smith@personal.com"
  }
}
```

In this more complex example, nested and list data structures are restructured into a new format, illustrating how model mapping can handle diverse and nested data effectively.

#### 3. Flattening Nested Data 

Flattening deeply nested data structures into a simpler, one-level object is a common requirement, especially for APIs that require simplified data formats or when preparing data for easier analysis. This example demonstrates how to use the `model_mapping` function to flatten a complex nested JSON object into a single-level structure.

##### Original  Data

```json
{
  "user": {
    "id": "12345",
    "profile": {
      "name": {
        "first": "Emily",
        "last": "Johnson"
      },
      "education": {
        "university": {
          "name": "State University",
          "degree": {
            "type": "BSc",
            "field": "Computer Science",
            "year": 2022
          }
        }
      }
    },
    "contact": {
      "emails": [
        "emily.johnson@university.com",
        "ejohnson@example.com"
      ],
      "phone": "555-1234"
    }
  }
}
```

##### Model Mapping (Option 1: key-value notation)

```json
{
  "model_mapping": {
    "user_id": "user.id",
    "first_name": "user.profile.name.first",
    "last_name": "user.profile.name.last",
    "university_name": "user.profile.education.university.name",
    "degree_type": "user.profile.education.university.degree.type",
    "degree_field": "user.profile.education.university.degree.field",
    "graduation_year": "user.profile.education.university.degree.year",
    "emails": "user.contact.emails",
    "primary_phone": "user.contact.phone"
  },
  "inverted": false
}
```

##### Model Mapping (Option 2: list of from-to dictionaries)

```json
{
  "model_mapping": [
    {
      "from_path": "user.id",
      "to_path": "user_id"
    },
    {
      "from_path": "user.profile.name.first",
      "to_path": "first_name"
    },
    {
      "from_path": "user.profile.name.last",
      "to_path": "last_name"
    },
    {
      "from_path": "user.profile.education.university.name",
      "to_path": "university_name"
    },
    {
      "from_path": "user.profile.education.university.degree.type",
      "to_path": "degree_type"
    },
    {
      "from_path": "user.profile.education.university.degree.field",
      "to_path": "degree_field"
    },
    {
      "from_path": "user.profile.education.university.degree.year",
      "to_path": "graduation_year"
    },
    {
      "from_path": "user.contact.emails",
      "to_path": "emails"
    },
    {
      "from_path": "user.contact.phone",
      "to_path": "primary_phone"
    }
  ],
  "inverted": false
}
```

##### Transformed  Data

```json
{
  "user_id": "12345",
  "first_name": "Emily",
  "last_name": "Johnson",
  "university_name": "State University",
  "degree_type": "BSc",
  "degree_field": "Computer Science",
  "graduation_year": 2022,
  "emails": [
    "emily.johnson@university.com",
    "ejohnson@example.com"
  ],
  "primary_phone": "555-1234"
}
```


For full reference of the workflow schema, refer to this [reference](schema_doc.html).
